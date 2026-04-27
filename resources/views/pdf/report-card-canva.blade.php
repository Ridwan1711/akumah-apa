@php
$canva = $templateConfig['canva'] ?? ['html' => '', 'css' => ''];
$html = $canva['html'] ?? '';
$css = $canva['css'] ?? '';

$replacements = [
    'prop_semester' => e(($semester->academicYear->name ?? '') . ' - ' . ($semester->name ?? '')),
    'prop_student_name' => e($student->full_name),
    'prop_student_nis' => e($student->nis),
    'prop_student_class' => e($student->currentClass->name ?? '-'),
    'prop_grades_table' => view('pdf.partials.grades', ['grades' => $grades, 'avgScore' => $avgScore ?? null])->render(),
    'prop_tahfidz_table' => view('pdf.partials.tahfidz', ['tahfidzProgress' => $tahfidzProgress])->render(),
    'prop_violations_table' => view('pdf.partials.violations', ['violations' => $violations])->render(),
    'prop_notes' => '<div class="notes-box">' . e($reportCard->wali_kelas_notes ?? '-') . '</div>',
    'prop_qr' => !empty($qrCodeBase64) ? '<img src="data:image/png;base64,' . $qrCodeBase64 . '" alt="Verifikasi" style="width: 60px; height: 60px;" />' : '',
];

foreach ($replacements as $prop => $content) {
    $html = preg_replace(
        '/<[a-zA-Z][a-zA-Z0-9]*\b[^>]*data-prop="' . preg_quote($prop) . '"[^>]*>[\s\S]*?<\/[a-zA-Z][a-zA-Z0-9]*>/',
        $content,
        $html
    );
}
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Raport - {{ $student->full_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; padding: 20px; position: relative; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .summary-box { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-bottom: 16px; }
        .notes-box { border: 1px solid #ddd; padding: 10px; min-height: 60px; margin-bottom: 16px; }
        {!! $css !!}
    </style>
</head>
<body>
    {!! $html !!}
</body>
</html>
