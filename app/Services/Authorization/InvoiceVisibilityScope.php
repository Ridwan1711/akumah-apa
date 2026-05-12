<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Support\Authorization\Permissions;
use Illuminate\Database\Eloquent\Builder;

class InvoiceVisibilityScope
{
    /**
     * Maksimal baris tagihan per santri untuk permission {@see Permissions::INVOICE_VIEW_LIMITED}.
     */
    public const LIMITED_VIEW_MAX_INVOICES_PER_STUDENT = 3;

    public function applyToInvoiceQuery(Builder $query, User $user): Builder
    {
        if ($user->hasPermission(Permissions::INVOICE_VIEW_ALL)) {
            return $query;
        }

        $narrowed = false;

        if ($user->hasPermission(Permissions::INVOICE_VIEW_PENGURUS_DIVISION)) {
            $divisionCodes = $user->permissionScopes()
                ->where('permission_name', Permissions::INVOICE_VIEW_PENGURUS_DIVISION)
                ->where('scope_key', 'division_code')
                ->pluck('scope_value')
                ->all();

            $query->whereHas('student', function (Builder $studentQuery) use ($divisionCodes) {
                $studentQuery
                    ->whereDoesntHave('activePositions')
                    ->orWhereHas('activePositions', function (Builder $positionQuery) use ($divisionCodes) {
                        if (! empty($divisionCodes)) {
                            $positionQuery->whereIn('division_code', $divisionCodes);
                        }
                    });
            });
            $narrowed = true;
        } elseif ($user->hasPermission(Permissions::INVOICE_VIEW_NON_PENGURUS)) {
            $query->whereHas('student', function (Builder $studentQuery) {
                $studentQuery->whereDoesntHave('activePositions');
            });
            $narrowed = true;
        } elseif ($user->hasPermission(Permissions::INVOICE_VIEW_LIMITED)) {
            $narrowed = true;
        }

        if (! $narrowed) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission(Permissions::INVOICE_VIEW_LIMITED)) {
            $this->applyInvoiceViewLimitedPerStudentCap($query);
        }

        return $query;
    }

    /**
     * Per santri: hanya tagihan yang termasuk N paling baru (urut due_date lalu created_at).
     */
    private function applyInvoiceViewLimitedPerStudentCap(Builder $query): void
    {
        $max = self::LIMITED_VIEW_MAX_INVOICES_PER_STUDENT;

        $base = clone $query;
        $base->reorder();
        $base->getQuery()->offset = null;
        $base->getQuery()->limit = null;
        $base->select(['invoices.id', 'invoices.student_id', 'invoices.due_date', 'invoices.created_at']);

        $innerSql = $base->toSql();
        $innerBindings = $base->getBindings();

        $rankedSql = 'SELECT id FROM (
            SELECT fi.id,
                ROW_NUMBER() OVER (
                    PARTITION BY fi.student_id
                    ORDER BY (fi.due_date IS NULL) ASC, fi.due_date DESC, fi.created_at DESC, fi.id DESC
                ) AS rn
            FROM ('.$innerSql.') AS fi
        ) AS ranked WHERE ranked.rn <= ?';

        $query->whereRaw('invoices.id IN ('.$rankedSql.')', array_merge($innerBindings, [$max]));
    }
}
