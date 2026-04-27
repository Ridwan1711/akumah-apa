@php
$style = $templateConfig['style'] ?? [];
$logoDataUri = null;
if (!empty($templateConfig['images']['logo'])) {
    $logoPath = storage_path('app/public/' . $templateConfig['images']['logo']);
    if (file_exists($logoPath)) {
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
}
@endphp
<div class="header" style="border-bottom-color: {{ $style['header_border_color'] ?? '#333' }};">
    @if($logoDataUri)
        <img src="{{ $logoDataUri }}" alt="" style="max-height: 50px; margin-bottom: 8px;" />
    @endif
    <h1>RAPORT DINIYAH</h1>
    <h2>{{ $semester->academicYear->name ?? '' }} - {{ $semester->name }}</h2>
</div>
