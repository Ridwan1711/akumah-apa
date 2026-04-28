@php
$templateConfig = $templateConfig ?? \App\Models\ReportCardTemplate::defaultConfig();
$style = $templateConfig['style'] ?? [];
$layout = $templateConfig['layout'] ?? ['header', 'info', 'grades', 'violations', 'notes', 'footer', 'qr'];
$blocks = $templateConfig['blocks'] ?? [];
$fontFamily = $style['font_family'] ?? 'DejaVu Sans';
$fontSize = $style['font_size'] ?? 11;
$primaryColor = $style['primary_color'] ?? '#1a1a1a';
$headerBg = $style['header_bg'] ?? '#f0f0f0';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Raport - {{ $student->full_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: {{ $fontFamily }}, sans-serif; font-size: {{ $fontSize }}px; color: {{ $primaryColor }}; padding: 20px; position: relative; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid {{ $style['header_border_color'] ?? '#333' }}; padding-bottom: 12px; }
        .header h1 { font-size: 18px; margin-bottom: 2px; }
        .header h2 { font-size: 14px; font-weight: normal; color: #555; }
        .info-grid { display: table; width: 100%; margin-bottom: 16px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 120px; padding: 2px 8px 2px 0; color: #666; }
        .info-value { display: table-cell; padding: 2px 0; font-weight: bold; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 13px; font-weight: bold; margin-bottom: 6px; padding: 4px 8px; background: {{ $headerBg }}; border-left: 3px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .summary-box { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-bottom: 16px; border-radius: 4px; }
        .notes-box { border: 1px solid #ddd; padding: 10px; min-height: 60px; margin-bottom: 16px; }
        .footer { margin-top: 30px; display: table; width: 100%; }
        .footer-col { display: table-cell; width: 50%; text-align: center; padding-top: 60px; }
        .footer-line { border-top: 1px solid #333; display: inline-block; width: 160px; padding-top: 4px; }
        .qr-block { }
    </style>
</head>
<body>
    @foreach($layout as $blockName)
        @if(($blocks[$blockName]['visible'] ?? true) && in_array($blockName, ['header', 'info', 'grades', 'violations', 'notes', 'footer', 'qr']))
            @include('pdf.partials.' . $blockName)
        @endif
    @endforeach
</body>
</html>
