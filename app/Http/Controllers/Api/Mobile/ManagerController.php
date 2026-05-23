<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ManagerClientResource;
use App\Http\Resources\Mobile\RegistrationRequestResource;
use App\Http\Resources\Mobile\UserResource;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $manager = $request->user();
        abort_unless($manager->canManageClients(), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $manager->visibleClients()
            ->with(['supportMessages' => fn ($query) => $query->with('sender')->orderBy('created_at')->orderBy('id')])
            ->withCount(['orders', 'supportMessages'])
            ->withCount([
                'supportMessages as unread_messages_count' => fn (Builder $query) => $query
                    ->where('sender_id', '!=', $manager->id)
                    ->whereNull('read_at'),
            ])
            ->when(filled($data['q'] ?? null), function (Builder $query) use ($data): void {
                $needle = '%'.preg_replace('/\s+/u', '%', trim((string) $data['q'])).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query
                        ->where('name', 'like', $needle)
                        ->orWhere('company', 'like', $needle)
                        ->orWhere('phone', 'like', $needle)
                        ->orWhere('email', 'like', $needle);
                });
            });

        $clients = $query
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        return response()->json([
            'data' => ManagerClientResource::collection($clients->getCollection())->resolve(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function registrationRequests(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageClients(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::in([
                RegistrationRequest::STATUS_PENDING,
                RegistrationRequest::STATUS_APPROVED,
                RegistrationRequest::STATUS_REJECTED,
                'all',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $data['status'] ?? RegistrationRequest::STATUS_PENDING;

        $requests = RegistrationRequest::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        return response()->json([
            'data' => RegistrationRequestResource::collection($requests->getCollection())->resolve(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function approveRegistrationRequest(Request $request, RegistrationRequest $registrationRequest): JsonResponse
    {
        $manager = $request->user();
        abort_unless($manager->canManageClients(), 403);
        abort_if($registrationRequest->status !== RegistrationRequest::STATUS_PENDING, 404);

        $data = $request->validate([
            'manager_id' => ['nullable', 'integer'],
        ]);

        $assignedManagerId = $this->resolveAssignedManagerId($manager, $data['manager_id'] ?? null);

        if (User::query()->where('login', $registrationRequest->login)->exists()) {
            throw ValidationException::withMessages([
                'login' => ['Логин из заявки уже занят.'],
            ]);
        }

        if (User::query()->where('email', $registrationRequest->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email из заявки уже используется.'],
            ]);
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
            'approved_by' => $manager->id,
            'approved_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $user->loadMissing('priceProfile');

        return response()->json([
            'request' => (new RegistrationRequestResource($registrationRequest->fresh()))->resolve(),
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function rejectRegistrationRequest(Request $request, RegistrationRequest $registrationRequest): JsonResponse
    {
        $manager = $request->user();
        abort_unless($manager->canManageClients(), 403);
        abort_if($registrationRequest->status !== RegistrationRequest::STATUS_PENDING, 404);

        $registrationRequest->update([
            'status' => RegistrationRequest::STATUS_REJECTED,
            'approved_by' => $manager->id,
            'approved_user_id' => null,
            'approved_at' => now(),
        ]);

        return response()->json([
            'request' => (new RegistrationRequestResource($registrationRequest->fresh()))->resolve(),
        ]);
    }

    private function resolveAssignedManagerId(User $user, mixed $managerId): int
    {
        if ($user->isManager()) {
            return $user->id;
        }

        abort_unless($user->isAdmin(), 403);

        $managerId = (int) $managerId;

        if ($managerId < 1) {
            throw ValidationException::withMessages([
                'manager_id' => ['Для заявки нужно выбрать менеджера.'],
            ]);
        }

        $exists = User::query()
            ->whereKey($managerId)
            ->where('is_manager', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'manager_id' => ['Выберите действующего менеджера.'],
            ]);
        }

        return $managerId;
    }
}
