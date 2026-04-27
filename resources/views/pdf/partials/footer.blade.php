@php
$imgDataUri = function ($key) use ($templateConfig) {
    $path = $templateConfig['images'][$key] ?? null;
    if (!$path) return null;
    $full = storage_path('app/public/' . $path);
    if (!file_exists($full)) return null;
    $mime = mime_content_type($full) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full));
};
$sigWali = $imgDataUri('signature_wali');
$sigKepala = $imgDataUri('signature_kepala');
$stamp = $imgDataUri('stamp');
@endphp
<div class="footer">
    <div class="footer-col">
        @if($sigWali)
            <img src="{{ $sigWali }}" alt="" style="max-height: 50px; margin-bottom: 4px;" />
        @endif
        <div class="footer-line">Wali Kelas</div>
    </div>
    <div class="footer-col">
        @if($sigKepala)
            <img src="{{ $sigKepala }}" alt="" style="max-height: 50px; margin-bottom: 4px;" />
        @endif
        @if($stamp)
            <img src="{{ $stamp }}" alt="" style="max-height: 60px; margin-bottom: 4px;" />
        @endif
        <div class="footer-line">Kepala Madrasah</div>
    </div>
</div>
