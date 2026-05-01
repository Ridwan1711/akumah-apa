<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Keterangan — {{ $certificate->certificate_number }}</title>
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

        /* ── KOP SURAT: pakai table ── */
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
            max-lines: 1;
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
            padding: 22pt 28pt 24pt 28pt;
        }

        /* ── Identitas Dokumen ── */
        .doc-identity {
            text-align: center;
            margin-bottom: 16pt;
            padding-bottom: 10pt;
            border-bottom: 1.5pt solid #b89a4e;
        }

        .doc-title {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #0d2045;
            margin-bottom: 3pt;
        }

        .doc-number {
            font-size: 9pt;
            color: #4a4a6a;
            letter-spacing: 0.06em;
        }

        .doc-number strong {
            color: #153268;
        }

        /* ── Meta Data: tabel 4 kolom ── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 16pt;
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
            width: 60pt;
        }

        .meta-table .meta-value {
            color: #0d2045;
            font-weight: bold;
            width: 100pt;
        }

        /* Badge inline */
        .badge {
            display: inline;
            padding: 1pt 6pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1pt solid #93c5fd;
            color: #1d4ed8;
            background: #dbeafe;
        }

        .badge-official {
            border: 1pt solid #6ee7b7;
            color: #065f46;
            background: #d1fae5;
        }

        /* ── Teks ── */
        .opening {
            font-size: 10.5pt;
            color: #4a4a6a;
            font-style: italic;
            margin-bottom: 6pt;
        }

        .subject-name {
            font-size: 13pt;
            font-weight: bold;
            color: #0d2045;
            margin-bottom: 3pt;
        }

        .subject-desc {
            font-size: 10pt;
            margin-bottom: 14pt;
            color: #1c1c2e;
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
            margin-bottom: 16pt;
        }

        .data-table thead tr {
            background: #0d2045;
            color: #ffffff;
        }

        .data-table thead th {
            padding: 7pt 10pt;
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
            padding: 6pt 10pt;
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

        .jam-badge {
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

        /* ── Tabel Detail Pengurus ── */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 16pt;
            border: 1pt solid #bbbbd8;
        }

        .detail-table tr {
            border-bottom: 1pt solid #bbbbd8;
        }

        .detail-table tr:last-child {
            border-bottom: none;
        }

        .detail-table th {
            width: 150pt;
            padding: 7pt 12pt;
            background: #f0eeea;
            color: #4a4a6a;
            font-weight: bold;
            font-size: 9.5pt;
            text-align: left;
            border-right: 1pt solid #bbbbd8;
            vertical-align: middle;
        }

        .detail-table td {
            padding: 7pt 12pt;
            color: #1c1c2e;
            font-weight: bold;
            vertical-align: middle;
        }

        /* ── Catatan Penutup ── */
        .closing-note {
            font-size: 10pt;
            color: #4a4a6a;
            font-style: italic;
            margin-bottom: 24pt;
            padding-left: 10pt;
            border-left: 3pt solid #b89a4e;
        }

        /* ── Tanda Tangan ── */
        .sig-outer {
            width: 100%;
            border-collapse: collapse;
        }

        .sig-outer td {
            border: none;
            padding: 0;
        }

        .sig-spacer {
            width: 58%;
        }

        .sig-block {
            width: 42%;
            text-align: center;
            vertical-align: top;
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
            max-width: 78pt;
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

        /* ── Footer ── */
        .page-footer {
            margin-top: 20pt;
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

        {{-- ════════════════════ KOP SURAT ════════════════════ --}}
        <table class="kop-table" cellspacing="0" cellpadding="0">
            <tr>
                <td class="kop-logo-cell">
                    <div class="logo-circle">
                        <img src="{{ public_path('/logo.png') }}" width="100%" height="100%" alt="Logo Institusi"
                            style="vertical-align:middle;" onerror="this.style.display='none'" />
                    </div>
                </td>
                <td class="kop-text-cell">
                    <div class="kop-instansi">Pondok Pesantren Manarul Huda Pusat</div>
                    <div class="kop-sub">Kp. Sukasirna &bull; Desa Sukarame &bull; Kec. Sukarame &bull; Kab. Tasikmalaya</div>
                    <div class="kop-contact">
                        Telp. (0265) 783-567 &bull; Email: info@manhoodpusat.com &bull; https://santri.manhoodpusat.com
                    </div>
                </td>
                <td class="kop-spacer-cell"></td>
            </tr>
        </table>

        <div class="rule-bar"></div>

        {{-- ════════════════════ KONTEN ════════════════════ --}}
        <div class="content-wrap">

            {{-- Identitas Dokumen --}}
            <div class="doc-identity">
                <div class="doc-title">Surat Keterangan</div>
                <div class="doc-number">Nomor: <strong>{{ $certificate->certificate_number }}</strong></div>
            </div>

            {{-- Meta: 4 kolom via <table> (print-safe) --}}
            <table class="meta-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="meta-label">Tipe Surat</td>
                    <td class="meta-value">
                        @if ($certificate->certificate_type === 'teacher')
                            <span class="badge">Tenaga Pendidik</span>
                        @else
                            <span class="badge badge-official">Pengurus</span>
                        @endif
                    </td>
                    <td class="meta-label">Tahun Ajaran</td>
                    <td class="meta-value">{{ $certificate->period?->academicYear?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Berlaku Sejak</td>
                    <td class="meta-value">{{ $certificate->valid_from?->locale('id')->translatedFormat('d F Y') ?? '—' }}</td>
                    <td class="meta-label">Berlaku Sampai</td>
                    <td class="meta-value">{{ $certificate->valid_until?->locale('id')->translatedFormat('d F Y') ?? '—' }}</td>
                </tr>
            </table>

            {{-- ══ GURU ══ --}}
            @if ($certificate->certificate_type === 'teacher')

                <p class="opening">Yang bertanda tangan di bawah ini, dengan ini menerangkan bahwa:</p>

                <p class="subject-name">
                    {{ $certificate->user?->name ?? ($certificate->payload['teacher_name'] ?? '—') }}
                </p>

                <p class="subject-desc">
                    adalah <strong>Tenaga Pendidik (Guru) Aktif</strong> pada institusi ini,
                    dengan cakupan penugasan mengajar sebagaimana tercantum dalam tabel berikut:
                </p>

                <div class="section-label">Rincian Penugasan Mengajar</div>

                <table class="data-table" cellspacing="0" cellpadding="0">
                    <thead>
                        <tr>
                            <th style="width:24pt; text-align:center;">No.</th>
                            <th style="width:120pt;">Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th class="center" style="width:80pt;">Total Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($certificate->payload['class_subject_assignments'] ?? []) as $i => $assignment)
                            <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
                                <td class="num">{{ $i + 1 }}</td>
                                <td>{{ $assignment['subject_name'] ?? '—' }}</td>
                                <td>{{ $assignment['class_names'] ?? ($assignment['class_name'] ?? '—') }}</td>
                                <td class="center">
                                    <span class="jam-badge">{{ $assignment['total_jam'] ?? ($assignment['target_jam'] ?? '—') }} Jam</span>
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="4">Belum ada data rincian kelas / mata pelajaran yang tersimpan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- ══ PENGURUS ══ --}}
            @else
                <p class="opening">Yang bertanda tangan di bawah ini, dengan ini menerangkan bahwa:</p>

                <p class="subject-name">
                    {{ $certificate->studentPosition?->student?->full_name ?? ($certificate->payload['student_name'] ?? '—') }}
                </p>

                <p class="subject-desc">
                    telah ditetapkan dan dilantik sebagai <strong>Pengurus</strong> pada institusi ini,
                    dengan rincian jabatan sebagaimana tercantum di bawah ini:
                </p>

                <div class="section-label">Rincian Jabatan &amp; Penugasan</div>

                <table class="detail-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <th>Jabatan</th>
                        <td>{{ $certificate->studentPosition?->position_type ?? ($certificate->payload['position_type'] ?? '—') }}
                        </td>
                    </tr>
                    <tr>
                        <th>Divisi</th>
                        <td>{{ $certificate->studentPosition?->division_code ?? ($certificate->payload['division_code'] ?? '—') }}
                        </td>
                    </tr>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <td>{{ $certificate->period?->academicYear?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Masa Berlaku</th>
                        <td>
                            {{ $certificate->valid_from?->locale('id')->translatedFormat('d F Y') ?? '—' }}
                            s.d.
                            {{ $certificate->valid_until?->locale('id')->translatedFormat('d F Y') ?? '—' }}
                        </td>
                    </tr>
                </table>

            @endif

            {{-- Catatan Penutup --}}
            <p class="closing-note">
                Surat keterangan ini dibuat dan diberikan kepada yang bersangkutan untuk dapat
                dipergunakan sebagaimana mestinya.
            </p>

            {{-- Tanda Tangan --}}
            <table class="sig-outer" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="sig-spacer"></td>
                    <td class="sig-block">
                        <p class="sig-date">Ditetapkan di Tasikmalaya, {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
                        <p class="sig-authority">{{ $certificate->principal_title ?: 'Pimpinan Pondok Pesantren' }}</p>
                        <div class="sig-stamp">
                            @if ($certificate->stamp_data_uri)
                                <img src="{{ $certificate->stamp_data_uri ?? public_path('stamp.png') }}" alt="Stempel">
                            @endif
                        </div>
                        <p class="sig-line">{{ $certificate->principal_name ?: 'H. Cecep \'Ilman Fahmi, SH.' }}</p>
                        <p class="sig-note">Nama Terang &amp; Stempel</p>
                    </td>
                </tr>
            </table>

        </div>{{-- /.content-wrap --}}

        {{-- ════════════════════ FOOTER ════════════════════ --}}
        <div class="page-footer">
            <table class="footer-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>Dokumen resmi &mdash; harap tidak diubah</td>
                    <td class="footer-center">{{ $certificate->certificate_number }}</td>
                    <td class="footer-right">Dicetak: {{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB</td>
                </tr>
            </table>
        </div>

    </div>{{-- /.page-wrap --}}
</body>

</html>
