<?php

namespace App\Services\Firebase;

use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountLinkSyncService
{
    public function syncUser(User $user): void
    {
        try {
            $database = app('firebase.database');
            $database->getReference('users/'.$user->id.'/account_links')->update([
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp_phone' => $user->whatsapp_phone,
                'google_connected' => (bool) $user->google_connected,
                'updated_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            $log = new SystemLogService();
            $log->warning('Firebase account link sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

