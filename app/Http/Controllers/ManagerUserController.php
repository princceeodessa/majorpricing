<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerUserController extends Controller
{
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin->isAdmin(), 403);
        abort_if($user->isAdmin(), 403, 'Нельзя удалить администратора.');
        abort_if($user->id === $admin->id, 403, 'Нельзя удалить самого себя.');

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('account.show')
            ->with('status', "Пользователь «{$name}» удалён.");
    }

    public function store(Request $request): RedirectResponse
    {
        $creator = $request->user();
        $accountType = $request->input('account_type', 'client');

        abort_unless(in_array($accountType, ['client', 'manager'], true), 422);
        abort_if($accountType === 'manager' && ! $creator?->isAdmin(), 403);

        $validated = $request->validate([
            'account_type' => ['nullable', Rule::in(['client', 'manager'])],
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
            'login' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique(User::class, 'login')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'manager_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Укажите имя пользователя.',
            'login.required' => 'Укажите логин.',
            'login.alpha_dash' => 'Логин может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
            'login.unique' => 'Такой логин уже используется.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Введите корректный email.',
            'email.unique' => 'Такой email уже используется.',
            'password.required' => 'Укажите пароль.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
        ]);

        $assignedManagerId = $this->resolveAssignedManagerId($request, $accountType, $validated['manager_id'] ?? null);

        $contactPeople = $this->normalizeStringList([
            ...($validated['contact_people'] ?? []),
            $validated['contact_person'] ?? null,
        ]);

        $messengers = $this->normalizeStringList([
            ...($validated['messengers'] ?? []),
            $validated['telegram'] ?? null,
        ]);

        $user = User::query()->create([
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
            'password' => $validated['password'],
            'manager_id' => $assignedManagerId,
            'price_profile_id' => null,
            'is_active' => $request->boolean('is_active', true),
            'is_manager' => $accountType === 'manager',
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        if ($accountType === 'client' && filled($validated['delivery_address'] ?? null)) {
            UserAddress::query()->create([
                'user_id' => $user->id,
                'title' => 'Основной адрес',
                'address' => trim($validated['delivery_address']),
                'is_default' => true,
                'sort_order' => 0,
            ]);
        }

        return redirect()
            ->route('account.show')
            ->with('status', $accountType === 'manager'
                ? 'Менеджер создан.'
                : 'Пользователь добавлен. Доступ готов к выдаче.');
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

    private function resolveAssignedManagerId(Request $request, string $accountType, mixed $managerId): ?int
    {
        $creator = $request->user();

        if ($accountType === 'manager') {
            return null;
        }

        if ($creator?->isManager()) {
            return $creator->id;
        }

        if (! $creator?->isAdmin()) {
            abort(403);
        }

        $managerId = (int) $managerId;

        if ($managerId < 1) {
            throw ValidationException::withMessages([
                'manager_id' => 'Для клиента нужно выбрать менеджера.',
            ]);
        }

        $exists = User::query()
            ->whereKey($managerId)
            ->where('is_manager', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'manager_id' => 'Выберите действующего менеджера.',
            ]);
        }

        return $managerId;
    }
}
