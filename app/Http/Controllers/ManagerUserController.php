<?php

namespace App\Http\Controllers;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagerUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:1500'],
            'login' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique(User::class, 'login')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'price_profile_id' => ['nullable', 'integer', Rule::exists(PriceProfile::class, 'id')],
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
            'price_profile_id.exists' => 'Выбранный прайс-профиль не найден.',
        ]);

        $priceProfileId = $validated['price_profile_id']
            ?? PriceProfile::query()->where('is_default', true)->value('id')
            ?? PriceProfile::query()->orderBy('column_index')->value('id');

        User::query()->create([
            'name' => trim($validated['name']),
            'company' => filled($validated['company'] ?? null) ? trim($validated['company']) : null,
            'contact_person' => filled($validated['contact_person'] ?? null) ? trim($validated['contact_person']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'telegram' => filled($validated['telegram'] ?? null) ? trim($validated['telegram']) : null,
            'delivery_address' => filled($validated['delivery_address'] ?? null) ? trim($validated['delivery_address']) : null,
            'login' => $validated['login'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'price_profile_id' => $priceProfileId,
            'is_active' => $request->boolean('is_active', true),
            'is_manager' => false,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('account.show')
            ->with('status', 'Пользователь добавлен. Доступ готов к выдаче.');
    }
}
