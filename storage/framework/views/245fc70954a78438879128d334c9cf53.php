<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Raport — <?php echo e($student->full_name); ?></title>
    <style>
        /* =====================================================
           PRINT-SAFE STYLESHEET
           Kompatibel: DomPDF, wkhtmltopdf, browser print
           TIDAK menggunakan: CSS Grid, Flexbox, CSS Variables,
           ::before / ::after, @import, border-radius kompleks
           ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11pt;
            color: #1c1c2e;
            line-height: 1.7;
            background: #faf9f5;
        }

        /* ── Wrapper Halaman ── */
        .page-wrap {
            width: 190mm;
            margin: 0 auto;
            background: #ffffff;
        }

        /* ── KOP SURAT ── */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3pt solid #b89a4e;
            background: #0d2045;
        }

        .kop-logo-cell {
            width: 72pt;
            padding: 10pt 8pt 10pt 12pt;
            vertical-align: middle;
            text-align: center;
        }

        .logo-circle {
            width: 56pt;
            height: 56pt;
            border: 2pt solid #b89a4e;
            border-radius: 50%;
            display: inline-block;
            text-align: center;
            vertical-align: middle;
            background: #ffffff;
            line-height: 52pt;
        }

        .kop-text-cell {
            padding: 10pt 8pt;
            vertical-align: middle;
            text-align: center;
        }

        .kop-instansi {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #e8d5a0;
            line-height: 1.3;
        }

        .kop-sub {
            font-size: 8.5pt;
            color: #aabbcc;
            margin-top: 3pt;
        }

        .kop-contact {
            font-size: 7.5pt;
            color: #7799bb;
            margin-top: 4pt;
        }

        .kop-spacer-cell {
            width: 72pt;
        }

        /* ── Garis Dekoratif ── */
        .rule-bar {
            width: 100%;
            height: 7pt;
            background: #153268;
            border-top: 2pt solid #b89a4e;
            border-bottom: 1pt solid #b89a4e;
        }

        /* ── Konten Utama ── */
        .content-wrap {
            padding: 20pt 28pt 18pt 28pt;
        }

        /* ── Identitas Dokumen ── */
        .doc-identity {
            text-align: center;
            margin-bottom: 14pt;
            padding-bottom: 8pt;
            border-bottom: 1.5pt solid #b89a4e;
        }

        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #0d2045;
            margin-bottom: 3pt;
        }

        .doc-subtitle {
            font-size: 9pt;
            color: #4a4a6a;
            letter-spacing: 0.05em;
        }

        .doc-subtitle strong {
            color: #153268;
        }

        /* ── Meta Identitas Santri ── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 14pt;
            border: 1pt solid #bbbbd8;
        }

        .meta-table td {
            padding: 5pt 10pt;
            border: 1pt solid #bbbbd8;
            vertical-align: middle;
        }

        .meta-table .meta-label {
            background: #f0eeea;
            color: #4a4a6a;
            width: 70pt;
        }

        .meta-table .meta-value {
            color: #0d2045;
            font-weight: bold;
            width: 110pt;
        }

        /* ── Label Seksi ── */
        .section-label {
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #b89a4e;
            margin-bottom: 6pt;
            border-bottom: 1pt solid #e8d5a0;
            padding-bottom: 3pt;
        }

        /* ── Tabel Data Utama ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 14pt;
        }

        .data-table thead tr {
            background: #0d2045;
            color: #ffffff;
        }

        .data-table thead th {
            padding: 6pt 10pt;
            text-align: left;
            font-size: 8.5pt;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: bold;
            border: 1pt solid #153268;
            color: #ffffff;
        }

        .data-table thead th.center {
            text-align: center;
        }

        .data-table tbody tr {
            border-bottom: 1pt solid #bbbbd8;
        }

        .data-table tbody tr.even {
            background: #f5f4ef;
        }

        .data-table tbody td {
            padding: 5pt 10pt;
            border: 1pt solid #d1d5db;
            vertical-align: middle;
        }

        .data-table tbody td.num {
            text-align: center;
            color: #4a4a6a;
            font-size: 8.5pt;
            width: 24pt;
        }

        .data-table tbody td.center {
            text-align: center;
        }

        .data-table tfoot tr {
            background: #f0eeea;
            font-weight: bold;
        }

        .data-table tfoot td {
            padding: 6pt 10pt;
            border: 1pt solid #d1d5db;
            color: #0d2045;
        }

        .grade-badge {
            display: inline;
            background: #0d2045;
            color: #e8d5a0;
            padding: 1pt 7pt;
            font-size: 8.5pt;
            font-weight: bold;
            border: 1pt solid #153268;
        }

        .empty-row td {
            text-align: center;
            color: #4a4a6a;
            font-style: italic;
            padding: 12pt;
        }

        /* ── Badge Pelanggaran ── */
        .badge {
            display: inline;
            padding: 1pt 6pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1pt solid #fde68a;
            color: #92400e;
            background: #fef3c7;
        }

        .badge-danger {
            border: 1pt solid #fca5a5;
            color: #991b1b;
            background: #fee2e2;
        }

        .badge-light {
            border: 1pt solid #93c5fd;
            color: #1d4ed8;
            background: #dbeafe;
        }

        /* ── Catatan Wali Kelas ── */
        .notes-box {
            border: 1pt solid #bbbbd8;
            padding: 10pt 12pt;
            margin-bottom: 18pt;
            background: #fafaf6;
        }

        .notes-content {
            font-size: 9.5pt;
            color: #1c1c2e;
            font-style: italic;
            line-height: 1.6;
        }

        .notes-empty {
            font-size: 9pt;
            color: #9999b8;
            font-style: italic;
        }

        /* ── Tanda Tangan ── */
        .sig-outer {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12pt;
        }

        .sig-outer td {
            border: none;
            padding: 0;
            vertical-align: top;
            text-align: center;
        }

        .sig-block {
            position: relative;
            width: 50%;
            padding: 0 10pt;
        }

        .penyesuai-high {
            height: ;
        }

        .sig-date {
            font-size: 9pt;
            color: #4a4a6a;
            margin-bottom: 3pt;
        }

        .sig-authority {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0d2045;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 10pt;
        }

        .sig-stamp {
            height: 42pt;
            margin-bottom: 8pt;
        }

        .sig-stamp img {
            max-width: 96pt;
            max-height: 42pt;
        }

        .sig-line {
            border-top: 1.2pt solid #1c1c2e;
            padding-top: 3pt;
            font-size: 8.5pt;
            color: #4a4a6a;
            font-weight: bold;
        }

        .sig-note {
            font-size: 7.5pt;
            color: #4a4a6a;
            margin-top: 3pt;
        }

        /* ── QR Verifikasi ── */
        .qr-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14pt;
            border-top: 1pt solid #bbbbd8;
            padding-top: 8pt;
        }

        .qr-table td {
            border: none;
            padding: 6pt 0 0 0;
            vertical-align: middle;
        }

        .qr-img-cell {
            width: 70pt;
            text-align: center;
        }

        .qr-img-cell img {
            width: 60pt;
            height: 60pt;
            border: 1pt solid #bbbbd8;
            background: #ffffff;
        }

        .qr-text-cell {
            padding-left: 10pt;
            font-size: 8pt;
            color: #4a4a6a;
        }

        .qr-text-cell strong {
            color: #0d2045;
        }

        .qr-url {
            font-family: 'Courier New', monospace;
            font-size: 7.5pt;
            color: #153268;
            word-break: break-all;
        }

        /* ── Footer ── */
        .page-footer {
            margin-top: 16pt;
            padding: 6pt 28pt;
            border-top: 1pt solid #bbbbd8;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            color: #9999b8;
        }

        .footer-table td {
            border: none;
            padding: 0;
        }

        .footer-center {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 8pt;
            color: #4a4a6a;
            letter-spacing: 0.1em;
        }

        .footer-right {
            text-align: right;
        }

        /* ── Print ── */
        @media print {
            body {
                background: #ffffff;
            }

            .page-wrap {
                margin: 0;
                width: 100%;
            }
        }

        @page {
            margin: 10mm 10mm 12mm 10mm;
            size: A4 portrait;
        }
    </style>
</head>

<body>
    <div class="page-wrap">

        
        <table class="kop-table" cellspacing="0" cellpadding="0">
            <tr>
                <td class="kop-logo-cell">
                    <div class="logo-circle">
                        <img src="<?php echo e(public_path('/logo.png')); ?>" width="100%" height="100%" alt="Logo Institusi"
                            style="vertical-align:middle;" onerror="this.style.display='none'" />
                    </div>
                </td>
                <td class="kop-text-cell">
                    <div class="kop-instansi">Pondok Pesantren Manarul Huda Pusat</div>
                    <div class="kop-sub">Kp. Sukasirna &bull; Desa Sukarame &bull; Kec. Sukarame &bull; Kab. Tasikmalaya
                    </div>
                    <div class="kop-contact">
                        Telp. (0265) 783-567 &bull; Email: info@manhoodpusat.com &bull; https://santri.manhoodpusat.com
                    </div>
                </td>
                <td class="kop-spacer-cell"></td>
            </tr>
        </table>

        <div class="rule-bar"></div>

        
        <div class="content-wrap">

            
            <div class="doc-identity">
                <div class="doc-title">Laporan Hasil Belajar Santri</div>
                <div class="doc-subtitle">
                    Tahun Ajaran <strong><?php echo e($semester->academicYear?->name ?? '—'); ?></strong>
                    &nbsp;&bull;&nbsp;
                    <strong><?php echo e($semester->name ?? '—'); ?></strong>
                </div>
            </div>

            
            <table class="meta-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="meta-label">Nama Santri</td>
                    <td class="meta-value"><?php echo e($student->full_name); ?></td>
                    <td class="meta-label">NIS</td>
                    <td class="meta-value"><?php echo e($student->nis); ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Kelas</td>
                    <td class="meta-value"><?php echo e($student->currentClass?->name ?? '—'); ?></td>
                    <td class="meta-label">Semester</td>
                    <td class="meta-value"><?php echo e($semester->name ?? '—'); ?></td>
                </tr>
            </table>

            
            <div class="section-label">Rincian Nilai Kitab</div>

            <table class="data-table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th style="width:24pt; text-align:center;">No.</th>
                        <th>Mata Pelajaran</th>
                        <th class="center" style="width:60pt;">Nilai</th>
                        <th class="center" style="width:60pt;">Huruf</th>
                        <th class="center" style="width:80pt;">Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $grades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $grade): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $score = (float) ($grade->score ?? 0);
                            $predicate = match (true) {
                                $score >= 90 => 'Sangat Baik',
                                $score >= 80 => 'Baik',
                                $score >= 70 => 'Cukup',
                                $score >= 60 => 'Kurang',
                                default => 'Sangat Kurang',
                            };
                        ?>
                        <tr class="<?php echo e($i % 2 === 1 ? 'even' : ''); ?>">
                            <td class="num"><?php echo e($i + 1); ?></td>
                            <td><?php echo e($grade->subject?->name ?? '—'); ?></td>
                            <td class="center"><?php echo e(rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.') ?: '0'); ?>

                            </td>
                            <td class="center">
                                <span class="grade-badge"><?php echo e($grade->grade_letter ?? '—'); ?></span>
                            </td>
                            <td class="center"><?php echo e($predicate); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr class="empty-row">
                            <td colspan="5">Belum ada nilai untuk semester ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if($grades->count() > 0): ?>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align:right;">Rata-rata Nilai</td>
                            <td class="center"><?php echo e($avgScore ?? '—'); ?></td>
                            <td colspan="2" class="center"><?php echo e($grades->count()); ?> Mata Pelajaran</td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>

            
            <?php
                $totalPoints = $violations->sum(fn($v) => $v->violationType?->points ?? 0);
            ?>

            <?php if($violations->count() > 0): ?>
                <div class="section-label">Catatan Pelanggaran (<?php echo e($totalPoints); ?> Poin)</div>

                <table class="data-table" cellspacing="0" cellpadding="0">
                    <thead>
                        <tr>
                            <th style="width:24pt; text-align:center;">No.</th>
                            <th style="width:80pt;">Tanggal</th>
                            <th>Jenis Pelanggaran</th>
                            <th class="center" style="width:80pt;">Kategori</th>
                            <th class="center" style="width:50pt;">Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $violations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="<?php echo e($i % 2 === 1 ? 'even' : ''); ?>">
                                <td class="num"><?php echo e($i + 1); ?></td>
                                <td><?php echo e(\Illuminate\Support\Carbon::parse($v->date)->locale('id')->translatedFormat('d M Y')); ?>

                                </td>
                                <td><?php echo e($v->violationType?->name ?? '—'); ?></td>
                                <td class="center">
                                    <?php
                                        $cat = $v->violationType?->category ?? '';
                                        $badgeClass = match ($cat) {
                                            'berat' => 'badge badge-danger',
                                            'ringan' => 'badge badge-light',
                                            default => 'badge',
                                        };
                                    ?>
                                    <span class="<?php echo e($badgeClass); ?>"><?php echo e($cat ?: '—'); ?></span>
                                </td>
                                <td class="center"><?php echo e($v->violationType?->points ?? 0); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;">Total Poin Pelanggaran</td>
                            <td class="center"><?php echo e($totalPoints); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>

            
            <div class="section-label">Catatan Wali Kelas</div>
            <div class="notes-box">
                <?php if(!empty($reportCard->wali_kelas_notes)): ?>
                    <div class="notes-content"><?php echo e($reportCard->wali_kelas_notes); ?></div>
                <?php else: ?>
                    <div class="notes-empty">Tidak Ada Catatan Wali Kelas.</div>
                <?php endif; ?>
            </div>

            
            <table class="sig-outer" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="sig-block">
                        <p class="sig-date">&nbsp;</p>
                        <p class="sig-authority">Wali Kelas</p>
                        <div class="sig-stamp">
                            <?php if(!empty($homeroomSignatureAbsolutePath)): ?>
                                <img src="<?php echo e($homeroomSignatureAbsolutePath); ?>" alt="Tanda tangan wali kelas">
                            <?php endif; ?>
                        </div>
                        <p class="sig-line"><?php echo e($homeroomTeacherName ?: '(.................................)'); ?></p>
                        <p class="sig-note">Nama Terang &amp; Tanda Tangan</p>
                    </td>
                    <td class="sig-block">
                        <p class="sig-date">Tasikmalaya, <?php echo e(now()->locale('id')->translatedFormat('d F Y')); ?></p>
                        <p class="sig-authority"><?php echo e($principalTitle ?: 'Pimpinan Pondok Pesantren'); ?></p>
                        <div class="sig-stamp">
                            <img src="<?php echo e(public_path('stamp.png')); ?>" width="40px"
                                height="40px" style="position: absolute;bottom:40%;left:50%;transform:translateX(-50%);"
                                alt="Stempel">
                        </div>
                        <p class="sig-line"><?php echo e($principalName ?: '(.................................)'); ?></p>
                        <p class="sig-note">Nama Terang &amp; Stempel</p>
                    </td>
                </tr>
            </table>

            
            <table class="qr-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="qr-img-cell">
                        <?php if(!empty($qrCodeBase64)): ?>
                            <img src="data:image/png;base64,<?php echo e($qrCodeBase64); ?>" alt="QR Verifikasi">
                        <?php endif; ?>
                    </td>
                    <td class="qr-text-cell">
                        <strong>Verifikasi Keaslian Dokumen</strong><br>
                        Pindai QR Code di samping atau kunjungi tautan berikut untuk memverifikasi keaslian raport ini:
                        <div class="qr-url"><?php echo e($verificationUrl); ?></div>
                    </td>
                </tr>
            </table>

        </div>

        
        <div class="page-footer">
            <table class="footer-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>Dokumen resmi &mdash; harap tidak diubah</td>
                    <td class="footer-center"><?php echo e($reportCard->verification_token); ?></td>
                    <td class="footer-right">Dicetak: <?php echo e(now()->locale('id')->translatedFormat('d F Y, H:i')); ?> WIB
                    </td>
                </tr>
            </table>
        </div>

    </div>
</body>

</html>
<?php /**PATH C:\Users\Server\Project gueh\siakad-manhood\resources\views/pdf/report-card.blade.php ENDPATH**/ ?>