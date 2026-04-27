<div class="info-grid">
    <div class="info-row"><span class="info-label">Nama</span><span class="info-value">{{ $student->full_name }}</span></div>
    <div class="info-row"><span class="info-label">NIS</span><span class="info-value">{{ $student->nis }}</span></div>
    <div class="info-row"><span class="info-label">Kelas</span><span class="info-value">{{ $student->currentClass->name ?? '-' }}</span></div>
</div>
