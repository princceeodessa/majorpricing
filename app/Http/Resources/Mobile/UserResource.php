<?php

namespace App\Http\Resources\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'city' => $this->city,
            'login' => $this->login,
            'email' => $this->email,
            'phone' => $this->phone,
            'telegram' => $this->telegram,
            'role' => $this->resolveRole(),
            'can_manage_clients' => $this->canManageClients(),
            'price_profile' => $this->priceProfile ? [
                'id' => $this->priceProfile->id,
                'name' => $this->priceProfile->name,
            ] : null,
        ];
    }

    private function resolveRole(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        if ($this->isManager()) {
            return 'manager';
        }

        return 'client';
    }
}
