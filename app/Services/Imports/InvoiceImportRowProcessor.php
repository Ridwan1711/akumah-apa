<?php

namespace App\Services\Imports;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\TingkatSekolah;
use App\Services\Finance\InvoiceImportSupport;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InvoiceImportRowProcessor
{
    /**
     * @return array{status: string, message: ?string}
     */
    public function process(array $data, string $_strategy, ?int $requestedByUserId = null): array
    {
        $validator = Validator::make($data, [
            'nis' => ['required', 'string', 'max:50'],
            'payment_type_code' => ['required', 'string', 'max:50'],
            'amount' => ['required'],
            'due_date' => ['required'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $nis = trim((string) $data['nis']);
        $student = Student::query()->where('nis', $nis)->first();
        if (! $student) {
            return ['status' => 'failed', 'message' => "Santri dengan NIS {$nis} tidak ditemukan."];
        }

        if ($student->status !== Student::STATUS_ACTIVE) {
            return ['status' => 'failed', 'message' => "Santri {$nis} tidak aktif."];
        }

        $code = strtoupper(trim((string) $data['payment_type_code']));
        $paymentType = PaymentType::query()->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (! $paymentType) {
            return ['status' => 'failed', 'message' => "Jenis pembayaran dengan kode '{$code}' tidak ditemukan."];
        }

        if (! $paymentType->is_active) {
            return ['status' => 'failed', 'message' => "Jenis pembayaran '{$code}' tidak aktif."];
        }

        $academicYear = $this->resolveAcademicYear($data);
        if (! $academicYear) {
            return ['status' => 'failed', 'message' => 'Tahun ajaran tidak valid (isi academic_year_id atau academic_year_name).'];
        }

        $month = $this->parseMonth($data['month'] ?? null);
        if ($paymentType->is_recurring && ($month === null || $month < 1 || $month > 12)) {
            return ['status' => 'failed', 'message' => 'Jenis berulang wajib memiliki bulan (1–12).'];
        }
        if (! $paymentType->is_recurring) {
            $month = null;
        }

        $amount = $this->parseDecimal($data['amount'] ?? null);
        if ($amount === null || $amount < 0) {
            return ['status' => 'failed', 'message' => 'Nominal amount tidak valid.'];
        }

        $dueDate = $this->parseDueDate($data['due_date']);
        if (! $dueDate) {
            return ['status' => 'failed', 'message' => 'Format due_date tidak valid (gunakan Y-m-d).'];
        }

        $manualExtraDiscount = max(0.0, (float) ($this->parseDecimal($data['discount_amount'] ?? 0) ?? 0.0));
        $autoDiscount = InvoiceImportSupport::studentMasterDiscount(
            $student->id,
            $paymentType->id,
            $academicYear->id,
            $amount
        );
        $discountTotal = min($amount, $autoDiscount + $manualExtraDiscount);
        $finalAmount = round($amount - $discountTotal, 2);
        if ($finalAmount < 0) {
            return ['status' => 'failed', 'message' => 'Total diskon melebihi nominal tagihan.'];
        }

        $breakdownOverride = $this->parseBreakdownJson($data['breakdown_json'] ?? null);

        try {
            $breakdown = InvoiceImportSupport::resolveBreakdownForInvoice($paymentType, $amount, $breakdownOverride);
        } catch (InvalidArgumentException $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        if ($this->invoiceSignatureExists($student->id, $paymentType->id, $academicYear->id, $month)) {
            return ['status' => 'skipped', 'message' => null];
        }

        try {
            $tingkatSnapshot = $student->formalTingkatEnrollmentForYear((int) $academicYear->id)?->tingkat_sekolah_id;
            if ($tingkatSnapshot === null && $student->is_kuliah) {
                $tingkatSnapshot = TingkatSekolah::query()
                    ->where('code', TingkatSekolah::CODE_KULIAH)
                    ->value('id');
            }

            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateNumber($paymentType->id, $month, $academicYear->id, $student->full_name),
                'student_id' => $student->id,
                'tingkat_sekolah_id' => $tingkatSnapshot,
                'payment_type_id' => $paymentType->id,
                'academic_year_id' => $academicYear->id,
                'month' => $month,
                'amount' => $amount,
                'discount_amount' => $discountTotal,
                'final_amount' => $finalAmount,
                'breakdown' => $breakdown,
                'status' => Invoice::STATUS_PENDING,
                'due_date' => $dueDate,
                'notes' => isset($data['notes']) && $data['notes'] !== null && $data['notes'] !== '' ? (string) $data['notes'] : null,
                'generated_by' => $requestedByUserId,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return ['status' => 'skipped', 'message' => null];
            }

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        InvoiceImportSupport::notifyInvoiceCreatedTargets($invoice);

        return ['status' => 'created', 'message' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveAcademicYear(array $data): ?AcademicYear
    {
        $idRaw = $data['academic_year_id'] ?? null;
        if ($idRaw !== null && $idRaw !== '' && is_numeric($idRaw)) {
            $id = (int) round((float) $idRaw);
            if ($id > 0) {
                return AcademicYear::query()->find($id);
            }
        }

        $name = isset($data['academic_year_name']) ? trim((string) $data['academic_year_name']) : '';
        if ($name === '') {
            return null;
        }

        return AcademicYear::query()->where('name', $name)->first();
    }

    protected function parseMonth(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) round((float) str_replace(',', '.', (string) $value));

        return $n >= 1 && $n <= 12 ? $n : null;
    }

    protected function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function parseDueDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $n = (float) $value;
            if ($n > 2000 && $n < 80000) {
                try {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($n))->startOfDay();
                } catch (\Throwable) {
                    // fall through
                }
            }
        }

        $str = trim((string) $value);
        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseBreakdownJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $str = trim((string) $raw);
        $decoded = json_decode($str, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function invoiceSignatureExists(int $studentId, int $paymentTypeId, int $academicYearId, ?int $month): bool
    {
        return Invoice::query()
            ->where('student_id', $studentId)
            ->where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->when(
                $month === null,
                fn ($q) => $q->whereNull('month'),
                fn ($q) => $q->where('month', $month)
            )
            ->exists();
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23505'
            || $sqlState === '23000'
            || $driverCode === '1062';
    }
}
