<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AuditLogHumanizer
{
    private function __construct() {}

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array{students: array<int, string>, invoices: array<int, array{student_name: string, type: string, month: int|null, amount: string}>}
     */
    public static function warmContext(Collection $logs): array
    {
        $studentIds = [];
        $invoiceIds = [];

        foreach ($logs as $log) {
            if ($log->module === 'student') {
                $studentIds[(int) $log->auditable_id] = true;
            }
            if ($log->module === 'invoice') {
                $invoiceIds[(int) $log->auditable_id] = true;
            }
            foreach ([$log->new_data ?? [], $log->old_data ?? []] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($row['student_id'])) {
                    $studentIds[(int) $row['student_id']] = true;
                }
                if ($log->module === 'payment' && isset($row['invoice_id'])) {
                    $invoiceIds[(int) $row['invoice_id']] = true;
                }
                if ($log->module === 'invoice' && isset($row['id'])) {
                    $invoiceIds[(int) $row['id']] = true;
                }
            }
        }

        $students = Student::query()
            ->whereIn('id', array_keys($studentIds))
            ->pluck('full_name', 'id')
            ->all();

        $invoiceMap = [];
        if ($invoiceIds !== []) {
            $invoices = Invoice::query()
                ->whereIn('id', array_keys($invoiceIds))
                ->with(['student:id,full_name', 'paymentType:id,name'])
                ->get(['id', 'student_id', 'payment_type_id', 'month', 'final_amount']);

            foreach ($invoices as $inv) {
                $invoiceMap[(int) $inv->id] = [
                    'student_name' => $inv->student?->full_name ?? 'Santri',
                    'type' => $inv->paymentType?->name ?? 'Tagihan',
                    'month' => $inv->month,
                    'amount' => number_format((float) $inv->final_amount, 0, ',', '.'),
                ];
            }
        }

        return [
            'students' => $students,
            'invoices' => $invoiceMap,
        ];
    }

    /**
     * @param  array{students: array<int, string>, invoices: array<int, array{student_name: string, type: string, month: int|null, amount: string}>}  $context
     * @return array{summary_line: string, time_relative: string, actor_label: string, technical_target: string}
     */
    public static function present(AuditLog $log, array $context): array
    {
        $students = $context['students'] ?? [];
        $invoices = $context['invoices'] ?? [];

        $new = is_array($log->new_data) ? $log->new_data : [];
        $old = is_array($log->old_data) ? $log->old_data : [];

        $summary = self::summarize($log, $new, $old, $students, $invoices);
        $created = $log->created_at instanceof Carbon ? $log->created_at : Carbon::parse($log->created_at);

        return [
            'summary_line' => $summary,
            'time_relative' => $created->locale('id')->diffForHumans(),
            'actor_label' => self::actorLabel($log),
            'technical_target' => class_basename((string) $log->auditable_type).' #'.$log->auditable_id,
        ];
    }

    private static function actorLabel(AuditLog $log): string
    {
        if ($log->user_id && $log->relationLoaded('user') && $log->user) {
            return 'Oleh '.$log->user->name;
        }

        return 'Catatan sistem (login otomatis / impor / job)';
    }

    /**
     * @param  array<int, string>  $students
     * @param  array<int, array{student_name: string, type: string, month: int|null, amount: string}>  $invoices
     */
    private static function summarize(AuditLog $log, array $new, array $old, array $students, array $invoices): string
    {
        $sid = (int) $log->auditable_id;
        $studentName = static function (array $n, array $o, int $fallbackId) use ($students): string {
            if (isset($n['full_name']) && $n['full_name'] !== '') {
                return (string) $n['full_name'];
            }
            if (isset($o['full_name']) && $o['full_name'] !== '') {
                return (string) $o['full_name'];
            }
            if (isset($n['student_id'])) {
                $id = (int) $n['student_id'];

                return $students[$id] ?? 'Santri #'.$id;
            }
            if (isset($o['student_id'])) {
                $id = (int) $o['student_id'];

                return $students[$id] ?? 'Santri #'.$id;
            }

            return $students[$fallbackId] ?? 'Santri #'.$fallbackId;
        };

        return match ($log->module) {
            'student' => self::summarizeStudent($log->action, $new, $old, $studentName($new, $old, $sid)),
            'tahfidzprogress' => self::summarizeTahfidz($log->action, $new, $old, $studentName($new, $old, 0)),
            'leavepermission' => self::summarizeLeave($log->action, $new, $old, $studentName($new, $old, 0)),
            'studentviolation' => self::summarizeViolation($log->action, $studentName($new, $old, 0)),
            'dormassignment' => self::summarizeDorm($log->action, $new, $studentName($new, $old, 0)),
            'invoice' => self::summarizeInvoice($log, $new, $studentName($new, $old, 0), $invoices),
            'payment' => self::summarizePayment($log->action, $new, $invoices),
            'studentdiscount' => self::summarizeStudentDiscount($log->action, $studentName($new, $old, 0)),
            'paymenttype' => self::summarizePaymentType($log->action, $new, $old),
            'guardian' => self::summarizeGuardian($log->action, $new, $old),
            'score' => self::summarizeScore($log->action, $studentName($new, $old, 0)),
            default => self::fallbackSummary($log),
        };
    }

    private static function summarizeStudent(string $action, array $new, array $old, string $name): string
    {
        if ($action === 'create') {
            return 'Santri baru terdaftar: '.$name;
        }
        if ($action === 'update' && isset($new['status'], $old['status']) && $new['status'] !== $old['status']) {
            return 'Status santri '.$name.': '.self::statusStudentLabel($old['status']).' → '.self::statusStudentLabel($new['status']);
        }
        if ($action === 'update') {
            return 'Data santri '.$name.' diperbarui';
        }
        if ($action === 'delete') {
            return 'Data santri dihapus dari sistem: '.$name;
        }

        return 'Perubahan data santri: '.$name;
    }

    private static function statusStudentLabel(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'Aktif',
            'alumni' => 'Alumni',
            'keluar' => 'Keluar',
            'wafat' => 'Wafat',
            default => (string) $status,
        };
    }

    private static function summarizeTahfidz(string $action, array $new, array $old, string $studentName): string
    {
        $juz = isset($new['juz']) ? (int) $new['juz'] : (isset($old['juz']) ? (int) $old['juz'] : null);
        $juzText = $juz !== null ? 'Juz '.$juz : 'hafalan';
        $type = isset($new['type']) ? (string) $new['type'] : (isset($old['type']) ? (string) $old['type'] : '');
        $typeLabel = match ($type) {
            'murojaah' => 'murojaah',
            'ziyadah' => 'ziyadah',
            default => $type !== '' ? $type : 'tahfidz',
        };

        if ($action === 'create') {
            return $studentName.' menyelesaikan setoran '.$juzText.' ('.$typeLabel.')';
        }
        if ($action === 'update') {
            return 'Setoran '.$juzText.' milik '.$studentName.' diperbarui ('.$typeLabel.')';
        }
        if ($action === 'delete') {
            return 'Catatan setoran '.$juzText.' untuk '.$studentName.' dihapus';
        }

        return 'Catatan tahfidz untuk '.$studentName.' diubah';
    }

    private static function summarizeLeave(string $action, array $new, array $old, string $studentName): string
    {
        $leave = $new['leave_date'] ?? $old['leave_date'] ?? null;
        $return = $new['return_date'] ?? $old['return_date'] ?? null;
        $daysLabel = '';
        if ($leave && $return) {
            $d1 = Carbon::parse($leave)->startOfDay();
            $d2 = Carbon::parse($return)->startOfDay();
            $days = max(1, $d1->diffInDays($d2) + 1);
            $daysLabel = ' ('.$days.' hari)';
        }

        if ($action === 'create') {
            return 'Izin pulang diajukan: '.$studentName.$daysLabel;
        }
        if ($action === 'update' && isset($new['status'], $old['status']) && $new['status'] !== $old['status']) {
            return 'Status izin '.$studentName.': '.self::leaveStatusLabel($old['status']).' → '.self::leaveStatusLabel($new['status']);
        }
        if ($action === 'update') {
            return 'Izin pulang '.$studentName.' diperbarui'.$daysLabel;
        }
        if ($action === 'delete') {
            return 'Izin pulang untuk '.$studentName.' dihapus';
        }

        return 'Perizinan pulang: '.$studentName.$daysLabel;
    }

    private static function leaveStatusLabel(mixed $s): string
    {
        return match ((string) $s) {
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => (string) $s,
        };
    }

    private static function summarizeViolation(string $action, string $studentName): string
    {
        if ($action === 'create') {
            return 'Pelanggaran dicatat untuk '.$studentName;
        }
        if ($action === 'update') {
            return 'Data pelanggaran diperbarui ('.$studentName.')';
        }
        if ($action === 'delete') {
            return 'Catatan pelanggaran dihapus ('.$studentName.')';
        }

        return 'Pelanggaran: '.$studentName;
    }

    private static function summarizeDorm(string $action, array $new, string $studentName): string
    {
        if ($action === 'create') {
            return $studentName.' ditugaskan ke kamar asrama';
        }
        if ($action === 'update' && array_key_exists('checkout_date', $new) && $new['checkout_date'] !== null) {
            return $studentName.' checkout dari asrama';
        }
        if ($action === 'update') {
            return 'Penugasan asrama '.$studentName.' diperbarui';
        }
        if ($action === 'delete') {
            return 'Penugasan asrama untuk '.$studentName.' dihapus';
        }

        return 'Asrama: '.$studentName;
    }

    /**
     * @param  array<int, array{student_name: string, type: string, month: int|null, amount: string}>  $invoices
     */
    private static function summarizeInvoice(AuditLog $log, array $new, string $studentName, array $invoices): string
    {
        $action = $log->action;
        $invId = isset($new['id']) ? (int) $new['id'] : (int) $log->auditable_id;
        $meta = isset($invoices[$invId]) ? $invoices[$invId] : null;
        $typePart = $meta ? $meta['type'] : 'Tagihan';
        $monthPart = $meta && $meta['month'] ? ' bulan '.$meta['month'] : '';

        if ($action === 'create') {
            return 'Tagihan '.$typePart.$monthPart.' dibuat untuk '.$studentName;
        }
        if ($action === 'update') {
            return 'Tagihan '.$typePart.' untuk '.$studentName.' diperbarui';
        }
        if ($action === 'delete') {
            return 'Tagihan dihapus untuk '.$studentName;
        }

        return 'Tagihan: '.$studentName;
    }

    /**
     * @param  array<int, array{student_name: string, type: string, month: int|null, amount: string}>  $invoices
     */
    private static function summarizePayment(string $action, array $new, array $invoices): string
    {
        $invId = isset($new['invoice_id']) ? (int) $new['invoice_id'] : null;
        $meta = $invId !== null && isset($invoices[$invId]) ? $invoices[$invId] : null;
        $who = $meta['student_name'] ?? 'Santri';
        $type = $meta['type'] ?? 'Tagihan';
        $amount = $meta['amount'] ?? null;

        if ($action === 'create') {
            $rp = $amount !== null ? ' Rp '.$amount : '';

            return 'Pembayaran '.$type.' tercatat untuk '.$who.$rp;
        }
        if ($action === 'update') {
            return 'Status pembayaran untuk '.$who.' ('.$type.') diperbarui';
        }
        if ($action === 'delete') {
            return 'Catatan pembayaran dihapus ('.$who.')';
        }

        return 'Pembayaran: '.$who;
    }

    private static function summarizeStudentDiscount(string $action, string $studentName): string
    {
        if ($action === 'create') {
            return 'Diskon santri ditambahkan untuk '.$studentName;
        }
        if ($action === 'update') {
            return 'Diskon santri diperbarui ('.$studentName.')';
        }
        if ($action === 'delete') {
            return 'Diskon santri dihapus ('.$studentName.')';
        }

        return 'Diskon santri: '.$studentName;
    }

    private static function summarizePaymentType(string $action, array $new, array $old): string
    {
        $name = isset($new['name']) ? (string) $new['name'] : (isset($old['name']) ? (string) $old['name'] : 'Jenis pembayaran');

        if ($action === 'create') {
            return 'Jenis pembayaran baru: '.$name;
        }
        if ($action === 'update') {
            return 'Jenis pembayaran diperbarui: '.$name;
        }
        if ($action === 'delete') {
            return 'Jenis pembayaran dihapus: '.$name;
        }

        return 'Jenis pembayaran: '.$name;
    }

    private static function summarizeGuardian(string $action, array $new, array $old): string
    {
        $name = isset($new['full_name']) ? (string) $new['full_name'] : (isset($old['full_name']) ? (string) $old['full_name'] : 'Wali santri');

        if ($action === 'create') {
            return 'Data wali ditambahkan: '.$name;
        }
        if ($action === 'update') {
            return 'Data wali diperbarui: '.$name;
        }
        if ($action === 'delete') {
            return 'Data wali dihapus: '.$name;
        }

        return 'Data wali: '.$name;
    }

    private static function summarizeScore(string $action, string $studentName): string
    {
        if ($action === 'create') {
            return 'Nilai diniyah dicatat untuk '.$studentName;
        }
        if ($action === 'update') {
            return 'Nilai diniyah diperbarui untuk '.$studentName;
        }
        if ($action === 'delete') {
            return 'Nilai diniyah dihapus untuk '.$studentName;
        }

        return 'Nilai diniyah: '.$studentName;
    }

    private static function fallbackSummary(AuditLog $log): string
    {
        $label = ucfirst(str_replace('_', ' ', $log->module));

        return match ($log->action) {
            'create' => $label.' — data baru ditambahkan',
            'update' => $label.' — data diperbarui',
            'delete' => $label.' — data dihapus',
            default => $label.' — '.$log->action,
        };
    }
}
