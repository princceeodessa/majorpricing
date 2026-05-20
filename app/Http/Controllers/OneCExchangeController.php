<?php

namespace App\Http\Controllers;

use App\Services\OneC\OneCCatalogExchangeService;
use App\Services\OneC\OneCExchangeStorage;
use App\Services\OneC\OneCSaleExchangeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class OneCExchangeController extends Controller
{
    public function __construct(
        private readonly OneCCatalogExchangeService $catalogExchangeService,
        private readonly OneCSaleExchangeService $saleExchangeService,
        private readonly OneCExchangeStorage $exchangeStorage,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $mode = $this->requestParam($request, 'mode');
        $type = $this->requestParam($request, 'type', 'catalog');
        $sessionKey = $this->exchangeSessionKey($request);

        try {
            return match ($mode) {
                'checkauth' => $this->checkAuth($request),
                default => $this->handleAuthorized($request, $type, $mode, $sessionKey),
            };
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('1C exchange failure', [
                'type' => $type,
                'mode' => $mode,
                'message' => $exception->getMessage(),
            ]);

            return $this->plainResponse("failure\n".$exception->getMessage(), 500);
        }
    }

    private function checkAuth(Request $request): Response
    {
        if (! $this->hasValidCredentials($request)) {
            return $this->plainResponse("failure\nUnauthorized", 401);
        }

        $request->session()->put('one_c_exchange_authenticated', true);
        $cookieName = (string) config('session.cookie', 'laravel_session');
        $sessionId = $request->session()->getId();
        $response = $this->plainResponse("success\n{$cookieName}\n{$sessionId}");

        $response->headers->setCookie(new Cookie(
            $cookieName,
            $sessionId,
            0,
            '/',
            null,
            false,
            false,
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    private function handleAuthorized(Request $request, string $type, string $mode, string $sessionKey): Response
    {
        if (! $this->isAuthorized($request)) {
            return $this->plainResponse("failure\nUnauthorized", 401);
        }

        return match ($mode) {
            'init' => $this->initExchange($type, $sessionKey),
            'file' => $this->storeFile($request, $type, $sessionKey),
            'import' => $this->importFiles($type, $sessionKey),
            'query' => $this->queryExchange($type, $sessionKey),
            'success' => $this->markSuccess($type, $sessionKey),
            default => $this->plainResponse("failure\nUnknown mode", 400),
        };
    }

    private function initExchange(string $type, string $sessionKey): Response
    {
        $this->exchangeStorage->resetUploadState($sessionKey, $type);

        return $this->plainResponse(sprintf(
            "zip=no\nfile_limit=%d",
            (int) config('integrations.one_c.file_limit', 10 * 1024 * 1024),
        ));
    }

    private function storeFile(Request $request, string $type, string $sessionKey): Response
    {
        $filename = $this->requestParam($request, 'filename', 'exchange.xml');
        $content = $request->getContent();

        if ($content === '') {
            return $this->plainResponse("failure\nEmpty file body", 400);
        }

        $this->exchangeStorage->appendFile($sessionKey, $type, $filename, $content);

        return $this->plainResponse('success');
    }

    private function importFiles(string $type, string $sessionKey): Response
    {
        if ($type === 'catalog') {
            $result = $this->catalogExchangeService->import($sessionKey);

            Log::info('1C catalog import completed', [
                'session_key' => substr($sessionKey, 0, 12),
                'files' => $result['files'],
                'categories' => $result['categories'],
                'products' => $result['products'],
                'prices' => $result['prices'],
                'offers_without_products' => $result['offers_without_products'],
                'warnings' => $result['warnings'],
            ]);
        } elseif ($type === 'sale') {
            $this->saleExchangeService->importStatuses($sessionKey);
        } else {
            return $this->plainResponse("failure\nUnknown type", 400);
        }

        return $this->plainResponse('success');
    }

    private function queryExchange(string $type, string $sessionKey): Response
    {
        if ($type !== 'sale') {
            return $this->plainResponse('success');
        }

        return new Response(
            $this->saleExchangeService->exportOrdersXml($sessionKey),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }

    private function markSuccess(string $type, string $sessionKey): Response
    {
        if ($type === 'sale') {
            $this->saleExchangeService->markExported($sessionKey);
            $this->exchangeStorage->clearType($sessionKey, $type);
        }

        return $this->plainResponse('success');
    }

    private function isAuthorized(Request $request): bool
    {
        if ($request->session()->get('one_c_exchange_authenticated') === true) {
            return true;
        }

        if ($this->hasValidCredentials($request)) {
            $request->session()->put('one_c_exchange_authenticated', true);

            return true;
        }

        return false;
    }

    private function hasValidCredentials(Request $request): bool
    {
        $expectedUser = (string) config('integrations.one_c.username');
        $expectedPassword = (string) config('integrations.one_c.password');

        if ($expectedUser === '' || $expectedPassword === '') {
            return false;
        }

        return hash_equals($expectedUser, (string) $request->getUser())
            && hash_equals($expectedPassword, (string) $request->getPassword());
    }

    private function plainResponse(string $content, int $status = 200): Response
    {
        return new Response($content, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function requestParam(Request $request, string $key, string $default = ''): string
    {
        $queryValue = $request->query($key);

        if (is_string($queryValue) && $queryValue !== '') {
            return $queryValue;
        }

        $inputValue = $request->input($key);

        if (is_string($inputValue) && $inputValue !== '') {
            return $inputValue;
        }

        return $default;
    }

    private function exchangeSessionKey(Request $request): string
    {
        $user = (string) $request->getUser();

        if ($user !== '') {
            return sha1(implode('|', [
                'one-c-exchange',
                $user,
                $this->requestParam($request, 'type', 'catalog'),
                (string) $request->ip(),
            ]));
        }

        $cookieName = (string) config('session.cookie', 'laravel_session');
        $cookieValue = $request->cookie($cookieName);

        if (is_string($cookieValue) && $cookieValue !== '') {
            return sha1($cookieValue);
        }

        return sha1((string) $request->session()->getId());
    }
}
