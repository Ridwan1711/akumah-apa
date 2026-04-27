<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Support\Authorization\Permissions;
use Illuminate\Database\Eloquent\Builder;

class InvoiceVisibilityScope
{
    public function applyToInvoiceQuery(Builder $query, User $user): Builder
    {
        if ($user->hasPermission(Permissions::INVOICE_VIEW_ALL)) {
            return $query;
        }

        if ($user->hasPermission(Permissions::INVOICE_VIEW_PENGURUS_DIVISION)) {
            $divisionCodes = $user->permissionScopes()
                ->where('permission_name', Permissions::INVOICE_VIEW_PENGURUS_DIVISION)
                ->where('scope_key', 'division_code')
                ->pluck('scope_value')
                ->all();

            return $query->whereHas('student', function (Builder $studentQuery) use ($divisionCodes) {
                $studentQuery
                    ->whereDoesntHave('activePositions')
                    ->orWhereHas('activePositions', function (Builder $positionQuery) use ($divisionCodes) {
                        if (! empty($divisionCodes)) {
                            $positionQuery->whereIn('division_code', $divisionCodes);
                        }
                    });
            });
        }

        if ($user->hasPermission(Permissions::INVOICE_VIEW_NON_PENGURUS)) {
            return $query->whereHas('student', function (Builder $studentQuery) {
                $studentQuery->whereDoesntHave('activePositions');
            });
        }

        return $query->whereRaw('1 = 0');
    }
}

