<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Raport - {{ $valid ? 'Sah' : 'Tidak Ditemukan' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
        @if($valid)
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Raport Sah</h1>
                <p class="text-gray-600 mb-6">Dokumen raport ini terdaftar dan dapat diverifikasi.</p>
                <div class="text-left bg-gray-50 rounded-lg p-4 space-y-2">
                    <p><span class="font-medium text-gray-500">Nama Santri:</span> {{ $reportCard->student->full_name ?? '-' }}</p>
                    <p><span class="font-medium text-gray-500">NIS:</span> {{ $reportCard->student->nis ?? '-' }}</p>
                    <p><span class="font-medium text-gray-500">Semester:</span> {{ $reportCard->semester->name ?? '-' }}</p>
                    <p><span class="font-medium text-gray-500">Tahun Ajaran:</span> {{ $reportCard->semester->academicYear->name ?? '-' }}</p>
                    <p><span class="font-medium text-gray-500">Tanggal Generate:</span> {{ $reportCard->generated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
            </div>
        @else
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 text-red-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Token Tidak Valid</h1>
                <p class="text-gray-600">Kode verifikasi tidak ditemukan atau telah kedaluwarsa.</p>
            </div>
        @endif
    </div>
</body>
</html>
