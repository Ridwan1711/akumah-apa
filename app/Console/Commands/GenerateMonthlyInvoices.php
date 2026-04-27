<?php

namespace App\Console\Commands;

use App\Models\FeeSchedule;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly {--month= : Month number (1-12), defaults to current month} {--year= : Year, defaults to current year}';

    protected $description = 'Generate monthly SPP invoices for all active students';

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);

        $activeSemester = Semester::where('is_active', true)->first();
        if (! $activeSemester) {
            $this->error('Tidak ada semester aktif.');
            return self::FAILURE;
        }

        $recurringTypes = PaymentType::where('is_recurring', true)
            ->where('is_active', true)
            ->get();

        if ($recurringTypes->isEmpty()) {
            $this->info('Tidak ada jenis pembayaran berulang yang aktif.');
            return self::SUCCESS;
        }

        $students = Student::where('status', Student::STATUS_ACTIVE)
            ->with('currentClass:id,name,level')
            ->get();

        $dueDate = now()->setYear($year)->setMonth($month)->endOfMonth()->toDateString();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($students, $recurringTypes, $activeSemester, $month, $dueDate, &$created, &$skipped) {
            foreach ($recurringTypes as $type) {
                foreach ($students as $student) {
                    $exists = Invoice::where('student_id', $student->id)
                        ->where('payment_type_id', $type->id)
                        ->where('academic_year_id', $activeSemester->academic_year_id)
                        ->where('month', $month)
                        ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $amount = $this->resolveAmount($type, $activeSemester->academic_year_id, $student->currentClass?->level);
                    $discount = $this->resolveDiscount($student->id, $type->id, $activeSemester->academic_year_id, $amount);

                    Invoice::create([
                        'invoice_number' => Invoice::generateNumber(),
                        'student_id' => $student->id,
                        'payment_type_id' => $type->id,
                        'academic_year_id' => $activeSemester->academic_year_id,
                        'semester_id' => $activeSemester->id,
                        'month' => $month,
                        'amount' => $amount,
                        'discount_amount' => $discount,
                        'final_amount' => $amount - $discount,
                        'status' => Invoice::STATUS_PENDING,
                        'due_date' => $dueDate,
                    ]);

                    $created++;
                }
            }
        });

        $this->info("Selesai. Dibuat: {$created}, Dilewati: {$skipped}");

        return self::SUCCESS;
    }

    private function resolveAmount(PaymentType $type, int $academicYearId, ?string $classLevel): float
    {
        $schedule = FeeSchedule::where('payment_type_id', $type->id)
            ->where('academic_year_id', $academicYearId)
            ->where(function ($q) use ($classLevel) {
                $q->where('class_level', $classLevel)->orWhereNull('class_level');
            })
            ->orderByRaw('class_level IS NULL ASC')
            ->first();

        return (float) ($schedule?->amount ?? $type->default_amount);
    }

    private function resolveDiscount(int $studentId, int $paymentTypeId, int $academicYearId, float $amount): float
    {
        $discount = StudentDiscount::where('student_id', $studentId)
            ->where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        return $discount ? $discount->calculateDiscount($amount) : 0;
    }
}
