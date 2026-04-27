@if(!empty($qrCodeBase64))
<div class="qr-block" style="position: absolute; bottom: 20px; right: 20px;">
    <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="Verifikasi" style="width: 60px; height: 60px;" />
</div>
@endif
