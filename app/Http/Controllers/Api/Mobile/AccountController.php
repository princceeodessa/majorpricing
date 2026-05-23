<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['addresses', 'manager', 'priceProfile']);

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            'addresses' => $user->addresses->map(fn ($address): array => [
                'id' => $address->id,
                'title' => $address->title,
                'address' => $address->address,
                'label' => $address->formattedLabel(),
                'is_default' => (bool) $address->is_default,
                'sort_order' => (int) $address->sort_order,
            ])->values()->all(),
            'manager' => $user->manager ? [
                'id' => $user->manager->id,
                'name' => $user->manager->name,
                'phone' => $user->manager->phone,
                'telegram' => $user->manager->telegram,
                'email' => $user->manager->email,
            ] : null,
        ]);
    }
}
