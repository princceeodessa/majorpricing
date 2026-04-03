<?php

namespace App\Http\Controllers;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('priceProfile');
        $priceProfiles = PriceProfile::query()
            ->orderBy('column_index')
            ->get();

        $managedUsers = collect();
        $managementStats = [
            'totalUsers' => 0,
            'activeUsers' => 0,
            'disabledUsers' => 0,
            'profilesCount' => $priceProfiles->count(),
        ];

        if ($user->isManager()) {
            $managedUsers = User::query()
                ->with('priceProfile')
                ->orderByDesc('id')
                ->get();

            $managementStats = [
                'totalUsers' => $managedUsers->count(),
                'activeUsers' => $managedUsers->where('is_active', true)->count(),
                'disabledUsers' => $managedUsers->where('is_active', false)->count(),
                'profilesCount' => $priceProfiles->count(),
            ];
        }

        return view('account.show', [
            'profile' => $user->priceProfile,
            'priceProfiles' => $priceProfiles,
            'managedUsers' => $managedUsers,
            'managementStats' => $managementStats,
        ]);
    }
}
