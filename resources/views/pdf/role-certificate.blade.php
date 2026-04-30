<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Keterangan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; line-height: 1.5; }
        .container { padding: 20px 28px; }
        .title { text-align: center; margin-bottom: 4px; font-weight: 700; font-size: 20px; }
        .subtitle { text-align: center; margin-bottom: 16px; font-size: 13px; }
        .meta { margin-bottom: 14px; font-size: 12px; }
        .meta div { margin-bottom: 3px; }
        .section-title { margin-top: 14px; margin-bottom: 6px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .signature { margin-top: 24px; text-align: right; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="title">SURAT KETERANGAN</div>
    <div class="subtitle">Nomor: {{ $certificate->certificate_number }}</div>

    <div class="meta">
        <div><strong>Tipe:</strong> {{ $certificate->certificate_type === 'teacher' ? 'Guru' : 'Pengurus' }}</div>
        <div><strong>Tahun Ajaran:</strong> {{ $certificate->period?->academicYear?->name ?? '-' }}</div>
        <div><strong>Berlaku:</strong> {{ $certificate->valid_from?->format('d-m-Y') ?? '-' }} s/d {{ $certificate->valid_until?->format('d-m-Y') ?? '-' }}</div>
    </div>

    @if($certificate->certificate_type === 'teacher')
        <p>Dengan ini menerangkan bahwa:</p>
        <p><strong>{{ $certificate->user?->name ?? ($certificate->payload['teacher_name'] ?? '-') }}</strong> adalah Guru aktif dengan cakupan mengajar berikut:</p>

        <table>
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Target Jam/Minggu</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($certificate->payload['class_subject_assignments'] ?? []) as $assignment)
                    <tr>
                        <td>{{ $assignment['class_name'] ?? '-' }}</td>
                        <td>{{ $assignment['subject_name'] ?? '-' }}</td>
                        <td>{{ $assignment['target_jam'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Belum ada rincian kelas/mapel tersimpan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <p>Dengan ini menerangkan bahwa:</p>
        <p><strong>{{ $certificate->studentPosition?->student?->full_name ?? ($certificate->payload['student_name'] ?? '-') }}</strong> ditetapkan sebagai Pengurus dengan rincian:</p>
        <table>
            <tbody>
                <tr>
                    <th style="width: 220px;">Jabatan/Posisi</th>
                    <td>{{ $certificate->studentPosition?->position_type ?? ($certificate->payload['position_type'] ?? '-') }}</td>
                </tr>
                <tr>
                    <th>Divisi</th>
                    <td>{{ $certificate->studentPosition?->division_code ?? ($certificate->payload['division_code'] ?? '-') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="signature">
        <div>{{ now()->translatedFormat('d F Y') }}</div>
        <div style="margin-top: 45px;">(_____________________)</div>
    </div>
</div>
</body>
</html>
