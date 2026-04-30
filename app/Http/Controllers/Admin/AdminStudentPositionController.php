<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionScope;
use App\Models\Student;
use App\Models\StudentPosition;
use App\Models\User;
use App\Services\RoleCertificateService;
use App\Support\Authorization\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminStudentPositionController extends Controller
{
    public function __construct(private RoleCertificateService $certificateService) {}

    public function index(Request $request): Response
    {
        $query = StudentPosition::query()
            ->with(['student:id,full_name,nis,current_class_id', 'student.currentClass:id,name'])
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = (string) $request->string('search');
                $builder->where(function ($inner) use ($search) {
                    $inner->where('position_type', 'ilike', "%{$search}%")
                        ->orWhere('division_code', 'ilike', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('nis', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($request->status === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($request->status === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when(
                $request->filled('division_code'),
                fn ($builder) => $builder->where('division_code', (string) $request->string('division_code'))
            )
            ->orderByDesc('is_active')
            ->orderBy('division_code')
            ->orderByDesc('id');

        $divisionOptions = StudentPosition::query()
            ->whereNotNull('division_code')
            ->where('division_code', '!=', '')
            ->distinct()
            ->orderBy('division_code')
            ->pluck('division_code')
            ->values();

        return Inertia::render('admin/student-positions/index', [
            'positions' => $query->paginate(15)->withQueryString(),
            'students' => Student::query()
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'nis']),
            'divisionOptions' => $divisionOptions,
            'financeUsers' => User::query()
                ->with([
                    'roles:id,name',
                    'permissions:id,name',
                    'permissionScopes' => fn ($scopeQuery) => $scopeQuery
                        ->where('permission_name', Permissions::INVOICE_VIEW_PENGURUS_DIVISION)
                        ->where('scope_key', 'division_code'),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'username', 'email'])
                ->filter(fn (User $user) => $user->hasPermission(Permissions::INVOICE_VIEW_PENGURUS_DIVISION))
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'division_codes' => $user->permissionScopes
                        ->pluck('scope_value')
                        ->filter(fn ($code) => is_string($code) && trim($code) !== '')
                        ->unique()
                        ->values()
                        ->all(),
                ]),
            'filters' => $request->only(['search', 'status', 'division_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'position_type' => ['required', 'string', 'max:100'],
            'division_code' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $position = StudentPosition::query()->create([
            ...$validated,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
        $position->load('student:id,full_name');
        $this->certificateService->issueForStudentPosition($position, $request->user()?->id);

        return redirect()->route('admin.student-positions.index')
            ->with('success', 'Posisi pengurus santri berhasil ditambahkan.');
    }

    public function update(Request $request, StudentPosition $studentPosition): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'position_type' => ['required', 'string', 'max:100'],
            'division_code' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $studentPosition->update($validated);
        $studentPosition->load('student:id,full_name');
        $this->certificateService->issueForStudentPosition($studentPosition, $request->user()?->id);

        return redirect()->route('admin.student-positions.index')
            ->with('success', 'Posisi pengurus santri berhasil diperbarui.');
    }

    public function destroy(StudentPosition $studentPosition): RedirectResponse
    {
        $studentPosition->delete();

        return redirect()->route('admin.student-positions.index')
            ->with('success', 'Posisi pengurus santri berhasil dihapus.');
    }

    public function activate(StudentPosition $studentPosition): RedirectResponse
    {
        $studentPosition->update([
            'is_active' => true,
            'ended_at' => null,
        ]);
        $studentPosition->load('student:id,full_name');
        $this->certificateService->issueForStudentPosition($studentPosition, request()->user()?->id);

        return redirect()->route('admin.student-positions.index')
            ->with('success', 'Posisi pengurus diaktifkan.');
    }

    public function deactivate(StudentPosition $studentPosition): RedirectResponse
    {
        $studentPosition->update([
            'is_active' => false,
            'ended_at' => $studentPosition->ended_at?->toDateString() ?? now()->toDateString(),
        ]);

        return redirect()->route('admin.student-positions.index')
            ->with('success', 'Posisi pengurus dinonaktifkan.');
    }

    public function updateDivisionAccess(Request $request, User $user): RedirectResponse
    {
        if (! $user->hasPermission(Permissions::INVOICE_VIEW_PENGURUS_DIVISION)) {
            throw ValidationException::withMessages([
                'division_codes' => ['User belum memiliki permission invoice.view_pengurus_division.'],
            ]);
        }

        $validated = $request->validate([
            'division_codes' => ['nullable', 'array'],
            'division_codes.*' => ['string', 'max:100'],
        ]);

        $allowedDivisionCodes = StudentPosition::query()
            ->whereNotNull('division_code')
            ->where('division_code', '!=', '')
            ->distinct()
            ->pluck('division_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter(fn ($code) => $code !== '')
            ->values();

        $divisionCodes = collect($validated['division_codes'] ?? [])
            ->map(fn ($code) => trim((string) $code))
            ->filter(fn ($code) => $code !== '')
            ->unique()
            ->values();

        $invalidCodes = $divisionCodes->diff($allowedDivisionCodes);
        if ($invalidCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'division_codes' => ['Terdapat kode divisi yang tidak valid: '.$invalidCodes->implode(', ')],
            ]);
        }

        PermissionScope::query()
            ->where('user_id', $user->id)
            ->where('permission_name', Permissions::INVOICE_VIEW_PENGURUS_DIVISION)
            ->where('scope_key', 'division_code')
            ->delete();

        if ($divisionCodes->isNotEmpty()) {
            PermissionScope::query()->insert(
                $divisionCodes->map(fn ($code) => [
                    'user_id' => $user->id,
                    'permission_name' => Permissions::INVOICE_VIEW_PENGURUS_DIVISION,
                    'scope_key' => 'division_code',
                    'scope_value' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }

        return redirect()->route('admin.student-positions.index')
            ->with('success', "Akses divisi untuk {$user->name} berhasil diperbarui.");
    }
}
