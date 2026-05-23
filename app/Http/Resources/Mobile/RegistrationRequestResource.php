<?php

namespace App\Http\Resources\Mobile;

use App\Models\RegistrationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistrationRequest
 */
class RegistrationRequestResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'contact_person' => $this->contact_person,
            'contact_people' => $this->contactPeopleList(),
            'phone' => $this->phone,
            'telegram' => $this->telegram,
            'messengers' => $this->messengersList(),
            'delivery_address' => $this->delivery_address,
            'login' => $this->login,
            'email' => $this->email,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_user_id' => $this->approved_user_id,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
