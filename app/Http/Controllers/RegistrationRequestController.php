<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RegistrationRequestController extends Controller
{
    public function create(): View
    {
        return view('auth.register-request');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_people' => ['nullable', 'array', 'max:10'],
            'contact_people.*' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'messengers' => ['nullable', 'array', 'max:10'],
            'messengers.*' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:1500'],
            'login' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique(User::class, 'login'),
                Rule::unique(RegistrationRequest::class, 'login')->where(
                    fn ($query) => $query->where('status', RegistrationRequest::STATUS_PENDING)
                ),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(RegistrationRequest::class, 'email')->where(
                    fn ($query) => $query->where('status', RegistrationRequest::STATUS_PENDING)
                ),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Укажите имя.',
            'login.required' => 'Укажите логин.',
            'login.alpha_dash' => 'Логин может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
            'login.unique' => 'Такой логин уже занят.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Введите корректный email.',
            'email.unique' => 'Такой email уже используется.',
            'password.required' => 'Укажите пароль.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
        ]);

        $contactPeople = $this->normalizeStringList([
            ...($validated['contact_people'] ?? []),
            $validated['contact_person'] ?? null,
        ]);

        $messengers = $this->normalizeStringList([
            ...($validated['messengers'] ?? []),
            $validated['telegram'] ?? null,
        ]);

        RegistrationRequest::query()->create([
            'name' => trim($validated['name']),
            'company' => filled($validated['company'] ?? null) ? trim($validated['company']) : null,
            'contact_person' => $contactPeople[0] ?? null,
            'contact_people' => $contactPeople !== [] ? $contactPeople : null,
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'telegram' => $messengers[0] ?? null,
            'messengers' => $messengers !== [] ? $messengers : null,
            'delivery_address' => filled($validated['delivery_address'] ?? null) ? trim($validated['delivery_address']) : null,
            'login' => $validated['login'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Заявка на регистрацию отправлена. Менеджер проверит данные и откроет доступ.');
    }

    public function approve(Request $request, RegistrationRequest $registrationRequest): RedirectResponse
    {
        abort_unless($request->user()?->canManageClients(), 403);

        abort_if($registrationRequest->status !== RegistrationRequest::STATUS_PENDING, 404);

        $validated = $request->validate([
            'manager_id' => ['nullable', 'integer'],
        ]);

        $assignedManagerId = $this->resolveAssignedManagerId($request, $validated['manager_id'] ?? null);

        if (User::query()->where('login', $registrationRequest->login)->exists()) {
            return back()->withErrors(['registration_request' => 'Логин из заявки уже занят.']);
        }

        if (User::query()->where('email', $registrationRequest->email)->exists()) {
            return back()->withErrors(['registration_request' => 'Email из заявки уже используется.']);
        }

        $user = User::query()->create([
            'name' => trim($registrationRequest->name),
            'company' => $registrationRequest->company ? trim((string) $registrationRequest->company) : null,
            'contact_person' => $registrationRequest->contactPeopleList()[0] ?? null,
            'contact_people' => $registrationRequest->contactPeopleList() !== [] ? $registrationRequest->contactPeopleList() : null,
            'phone' => $registrationRequest->phone ? trim((string) $registrationRequest->phone) : null,
            'telegram' => $registrationRequest->messengersList()[0] ?? null,
            'messengers' => $registrationRequest->messengersList() !== [] ? $registrationRequest->messengersList() : null,
            'delivery_address' => $registrationRequest->delivery_address ? trim((string) $registrationRequest->delivery_address) : null,
            'login' => $registrationRequest->login,
            'email' => $registrationRequest->email,
            'password' => $registrationRequest->password,
            'manager_id' => $assignedManagerId,
            'price_profile_id' => null,
            'is_active' => true,
            'is_manager' => false,
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        if (filled($registrationRequest->delivery_address)) {
            UserAddress::query()->create([
                'user_id' => $user->id,
                'title' => 'Основной адрес',
                'address' => trim((string) $registrationRequest->delivery_address),
                'is_default' => true,
                'sort_order' => 0,
            ]);
        }

        $registrationRequest->update([
            'status' => RegistrationRequest::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('account.show')
            ->with('status', 'Заявка подтверждена. Клиент создан и доступ активирован.');
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function normalizeStringList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function resolveAssignedManagerId(Request $request, mixed $managerId): int
    {
        $user = $request->user();

        if ($user->isManager()) {
            return $user->id;
        }

        if (! $user->isAdmin()) {
            abort(403);
        }

        $managerId = (int) $managerId;

        abort_unless(
            User::query()->whereKey($managerId)->where('is_manager', true)->exists(),
            422
        );

        return $managerId;
    }
}
