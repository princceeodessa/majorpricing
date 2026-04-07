<?php

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\OrderItem;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use XMLWriter;

class OneCSaleExchangeService
{
    public function __construct(
        private readonly OneCExchangeStorage $storage,
    ) {
    }

    public function exportOrdersXml(string $sessionKey): string
    {
        $orders = Order::query()
            ->with(['items.product', 'user'])
            ->where(function ($query): void {
                $query
                    ->whereNull('one_c_exported_at')
                    ->orWhereColumn('updated_at', '>', 'one_c_exported_at');
            })
            ->orderBy('id')
            ->get();

        $this->storage->rememberExportedOrderIds($sessionKey, $orders->pluck('id')->all());

        return $this->buildOrdersXml($orders);
    }

    public function markExported(string $sessionKey): int
    {
        $orderIds = $this->storage->pullExportedOrderIds($sessionKey);

        if ($orderIds === []) {
            return 0;
        }

        return Order::query()
            ->whereIn('id', $orderIds)
            ->update([
                'one_c_exported_at' => now(),
            ]);
    }

    /**
     * @return array{updated:int}
     */
    public function importStatuses(string $sessionKey): array
    {
        $updated = 0;

        foreach ($this->storage->xmlFiles($sessionKey, 'sale') as $xmlFile) {
            $xml = $this->storage->fileContents($xmlFile);

            if ($xml === null) {
                continue;
            }

            $xpath = $this->createXPath($xml);

            if (! $xpath) {
                continue;
            }

            foreach ($xpath->query('//*[local-name()="Документ"]') as $documentNode) {
                if (! $documentNode instanceof DOMElement) {
                    continue;
                }

                $documentId = $this->firstChildValue($xpath, $documentNode, 'Ид');
                $number = $this->firstChildValue($xpath, $documentNode, 'Номер');

                $order = null;

                if (filled($number)) {
                    $order = Order::query()->where('number', $number)->first();
                }

                if (! $order && filled($documentId)) {
                    $order = Order::query()->where('one_c_document_id', $documentId)->first();
                }

                if (! $order) {
                    continue;
                }

                $requisites = $this->collectRequisites($xpath, $documentNode);
                $status = $this->resolveOrderStatus($requisites['статус заказа'] ?? null, $requisites['отменен'] ?? null);
                $paymentStatus = $this->resolvePaymentStatus($requisites['оплачен'] ?? null, $requisites['статус оплаты'] ?? null);

                $order->forceFill([
                    'one_c_document_id' => $documentId ?: $order->one_c_document_id,
                    'status' => $status ?? $order->status,
                    'payment_status' => $paymentStatus ?? $order->payment_status,
                    'paid_amount' => $paymentStatus === 'paid' ? ($order->paid_amount ?? $order->total_amount) : $order->paid_amount,
                    'paid_at' => $paymentStatus === 'paid' ? ($order->paid_at ?? now()) : $order->paid_at,
                    'one_c_updated_at' => now(),
                    'manager_comment' => $requisites['комментарий'] ?? $order->manager_comment,
                ])->save();

                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function buildOrdersXml(Collection $orders): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->startElement('КоммерческаяИнформация');
        $xml->writeAttribute('ВерсияСхемы', '2.10');
        $xml->writeAttribute('ДатаФормирования', now()->format('Y-m-d\TH:i:s'));

        foreach ($orders as $order) {
            $xml->startElement('Документ');
            $xml->writeElement('Ид', $order->one_c_document_id ?: $order->number);
            $xml->writeElement('Номер', $order->number);
            $xml->writeElement('Дата', optional($order->placed_at)->format('Y-m-d') ?: now()->format('Y-m-d'));
            $xml->writeElement('Время', optional($order->placed_at)->format('H:i:s') ?: now()->format('H:i:s'));
            $xml->writeElement('ХозОперация', 'Заказ товара');
            $xml->writeElement('Роль', 'Продавец');
            $xml->writeElement('Валюта', 'RUB');
            $xml->writeElement('Курс', '1');
            $xml->writeElement('Сумма', $this->formatDecimal($order->total_amount));

            $xml->startElement('Контрагенты');
            $xml->startElement('Контрагент');
            $xml->writeElement('Ид', 'customer-'.$order->user_id);
            $xml->writeElement('Наименование', $order->customer_company ?: ($order->customer_name ?: $order->user?->name ?: 'Клиент сайта'));
            $xml->writeElement('Роль', 'Покупатель');

            $xml->startElement('Контакты');
            $this->writeContact($xml, 'Email', $order->customer_email ?: $order->user?->email);
            $this->writeContact($xml, 'ТелефонРабочий', $order->customer_phone);
            $this->writeContact($xml, 'ТелефонМобильный', $order->customer_phone);
            $this->writeContact($xml, 'АдресДоставки', $order->customer_delivery_address);
            $this->writeContact($xml, 'Telegram', $order->customer_telegram);
            $xml->endElement();

            $xml->endElement();
            $xml->endElement();

            $xml->startElement('Товары');
            foreach ($order->items as $item) {
                $this->writeOrderItem($xml, $item);
            }
            $xml->endElement();

            $xml->startElement('ЗначенияРеквизитов');
            $this->writeRequisite($xml, 'Статус заказа', $order->status);
            $this->writeRequisite($xml, 'Статус оплаты', $order->payment_status);
            $this->writeRequisite($xml, 'Комментарий', $order->comment);
            $this->writeRequisite($xml, 'Контактное лицо', $order->customer_contact_person);
            $this->writeRequisite($xml, 'Прайс-профиль', $order->price_profile_name);
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeOrderItem(XMLWriter $xml, OrderItem $item): void
    {
        $xml->startElement('Товар');
        $xml->writeElement('Ид', $item->product?->one_c_id ?: ('product-'.$item->product_id));

        if (filled($item->product?->vendor_code)) {
            $xml->writeElement('Артикул', $item->product->vendor_code);
        }

        $xml->writeElement('Наименование', $item->product_title);
        $xml->writeElement('ЦенаЗаЕдиницу', $this->formatDecimal($item->unit_price));
        $xml->writeElement('Количество', $this->formatDecimal($item->quantity, 3));
        $xml->writeElement('Сумма', $this->formatDecimal($item->line_total));
        $xml->writeElement('Единица', $item->measurement_value ?: 'шт');
        $xml->endElement();
    }

    private function writeContact(XMLWriter $xml, string $type, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $xml->startElement('Контакт');
        $xml->writeElement('Тип', $type);
        $xml->writeElement('Значение', $value);
        $xml->endElement();
    }

    private function writeRequisite(XMLWriter $xml, string $name, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $xml->startElement('ЗначениеРеквизита');
        $xml->writeElement('Наименование', $name);
        $xml->writeElement('Значение', $value);
        $xml->endElement();
    }

    /**
     * @return array<string, string>
     */
    private function collectRequisites(DOMXPath $xpath, DOMElement $documentNode): array
    {
        $result = [];

        foreach ($xpath->query('./*[local-name()="ЗначенияРеквизитов"]/*[local-name()="ЗначениеРеквизита"]', $documentNode) as $requisiteNode) {
            if (! $requisiteNode instanceof DOMElement) {
                continue;
            }

            $name = Str::lower(trim((string) $this->firstChildValue($xpath, $requisiteNode, 'Наименование')));
            $value = trim((string) $this->firstChildValue($xpath, $requisiteNode, 'Значение'));

            if ($name !== '' && $value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private function resolveOrderStatus(?string $status, ?string $isCanceled): ?string
    {
        if ($this->isTruthy($isCanceled)) {
            return 'canceled';
        }

        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === '' => null,
            str_contains($normalized, 'отмен') => 'canceled',
            str_contains($normalized, 'выполн'), str_contains($normalized, 'заверш') => 'completed',
            str_contains($normalized, 'обработ'), str_contains($normalized, 'принят'), str_contains($normalized, 'соглас') => 'processing',
            default => 'new',
        };
    }

    private function resolvePaymentStatus(?string $isPaid, ?string $paymentStatus): ?string
    {
        if ($this->isTruthy($isPaid)) {
            return 'paid';
        }

        $normalized = Str::lower(trim((string) $paymentStatus));

        return match (true) {
            $normalized === '' => null,
            str_contains($normalized, 'оплач') => 'paid',
            str_contains($normalized, 'ошиб') => 'failed',
            str_contains($normalized, 'отмен') => 'canceled',
            default => 'pending',
        };
    }

    private function isTruthy(?string $value): bool
    {
        $normalized = Str::lower(trim((string) $value));

        return in_array($normalized, ['true', '1', 'да', 'yes'], true);
    }

    private function createXPath(string $xml): ?DOMXPath
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = $this->normalizeLegacyEncoding($xml);

        if (! @$document->loadXML($xml, LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            return null;
        }

        return new DOMXPath($document);
    }

    private function normalizeLegacyEncoding(string $xml): string
    {
        if (! str_contains($xml, 'Р')) {
            return $xml;
        }

        $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $xml);

        if (! is_string($candidate) || $candidate === '' || ! mb_check_encoding($candidate, 'UTF-8')) {
            return $xml;
        }

        foreach (['<КоммерческаяИнформация', '<Документ', '<Товары', '<ЗначенияРеквизитов'] as $marker) {
            if (str_contains($candidate, $marker)) {
                return $candidate;
            }
        }

        return $xml;
    }

    private function firstChildValue(DOMXPath $xpath, DOMNode $contextNode, string $localName): ?string
    {
        $node = $xpath->query('./*[local-name()="'.$localName.'"]', $contextNode)->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function formatDecimal(mixed $value, int $precision = 2): string
    {
        return number_format((float) $value, $precision, '.', '');
    }
}
