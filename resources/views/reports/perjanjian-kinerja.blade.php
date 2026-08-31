@php
    $document = data_get($report, 'metadata.pk_document', []);
    $logoPath = public_path('images/logo-banjarnegara.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $first = $document['first_party'] ?? [];
    $second = $document['second_party'] ?? null;
    $groups = $document['performance_groups'] ?? [];
    $programs = $document['programs'] ?? [];
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] ?? 'Perjanjian Kinerja' }}</title>
    <style>
        @page { margin: 14mm 16mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; line-height: 1.55; }
        .sheet { min-height: 267mm; position: relative; }
        .page-break { page-break-before: always; }
        .letterhead { border-bottom: 3px double #111; display: table; padding: 0 8mm 6px; width: 100%; }
        .letterhead-logo { display: table-cell; vertical-align: middle; width: 23mm; }
        .letterhead-logo img { display: block; height: 20mm; margin: auto; width: auto; }
        .letterhead-copy { display: table-cell; padding-right: 23mm; text-align: center; vertical-align: middle; }
        .letterhead-copy .government { font-size: 12pt; }
        .letterhead-copy .office { font-size: 15pt; font-weight: 700; line-height: 1.2; text-transform: uppercase; }
        .letterhead-copy .address { font-size: 8.5pt; line-height: 1.35; margin-top: 3px; }
        h1 { font-size: 14pt; margin: 17px 0 18px; text-align: center; }
        p { margin: 0 0 9px; text-align: justify; }
        .party-table { border-collapse: collapse; margin: 5px 0 10px; width: 100%; }
        .party-table td { border: 1px solid #aaa; padding: 2px 7px; vertical-align: top; }
        .party-table .label { border-right: 0; width: 18%; }
        .party-table .separator { border-left: 0; border-right: 0; width: 2%; }
        .party-table .value { border-left: 0; }
        .party-role { font-size: 9.5pt; margin: -6px 0 8px; }
        .signature-date { margin: 12px 0 2px; text-align: right; }
        .signatures { display: table; margin-left: auto; width: 78%; }
        .signature { display: table-cell; text-align: center; vertical-align: top; width: 50%; }
        .signature .space { height: 48px; }
        .signature .name { font-weight: 700; text-decoration: underline; }
        .attachment-heading { margin-bottom: 12px; text-align: right; }
        .attachment-heading strong { display: block; font-size: 10.5pt; }
        table.matrix { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .matrix th, .matrix td { border: 1px solid #111; padding: 5px 6px; vertical-align: top; }
        .matrix th { background: #f1f1f1; font-size: 9pt; text-align: center; text-transform: uppercase; }
        .matrix td { font-size: 9pt; line-height: 1.38; }
        .matrix .number { text-align: center; width: 6%; }
        .matrix .performance { width: 35%; }
        .matrix .indicator { width: 42%; }
        .matrix .target { text-align: center; width: 17%; }
        .matrix .money { text-align: right; white-space: nowrap; width: 25%; }
        .matrix .note { text-align: center; width: 16%; }
        .group-label { color: #444; display: block; font-size: 7.5pt; font-weight: 700; letter-spacing: .05em; margin-bottom: 2px; text-transform: uppercase; }
        .program-table { margin-top: 15px; }
        .total-row td { background: #f1f1f1; font-weight: 700; }
        .source { color: #555; font-size: 8pt; margin-top: 7px; text-align: left; }
        .print-toolbar { align-items: center; background: #0f172a; border-radius: 999px; bottom: 22px; display: flex; gap: 8px; padding: 7px; position: fixed; right: 22px; z-index: 20; }
        .print-toolbar button { background: #fff; border: 0; border-radius: 999px; color: #0f172a; cursor: pointer; font-size: 13px; font-weight: 700; padding: 9px 16px; }
        @media print { .print-toolbar { display: none; } }
    </style>
</head>
<body>
@if($browserPrint ?? false)
    <div class="print-toolbar"><button type="button" onclick="window.print()">Cetak / Unduh PDF</button></div>
@endif

<section class="sheet">
    <div class="letterhead">
        <div class="letterhead-logo">@if($logo)<img src="{{ $logo }}" alt="Lambang Banjarnegara">@endif</div>
        <div class="letterhead-copy">
            <div class="government">PEMERINTAH KABUPATEN BANJARNEGARA</div>
            <div class="office">{{ ($document['is_bupati'] ?? false) ? 'BUPATI BANJARNEGARA' : ($document['office_name'] ?? 'PERANGKAT DAERAH') }}</div>
            <div class="address">{{ $document['address'] ?? 'Kabupaten Banjarnegara' }}@if(filled($document['contact'] ?? null))<br>{{ $document['contact'] }}@endif</div>
        </div>
    </div>

    <h1>{{ $document['title'] ?? 'PERJANJIAN KINERJA' }}</h1>

    @if($document['is_bupati'] ?? false)
        <p>Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan, akuntabel, dan berorientasi pada hasil, saya yang bertanda tangan di bawah ini:</p>
        <table class="party-table">
            <tr><td class="label">Nama</td><td class="separator">:</td><td class="value">{{ $first['name'] ?? '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td class="value">{{ $first['position'] ?? 'Bupati Banjarnegara' }}</td></tr>
        </table>
        <p>berjanji akan mewujudkan target kinerja yang seharusnya sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah sebagaimana telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.</p>
    @else
        <p>Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan, dan akuntabel serta berorientasi pada hasil, kami yang bertanda tangan di bawah ini:</p>
        <table class="party-table">
            <tr><td class="label">Nama</td><td class="separator">:</td><td class="value">{{ $first['name'] ?? '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td class="value">{{ $first['position'] ?? '-' }}</td></tr>
        </table>
        <div class="party-role">Selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong></div>
        <table class="party-table">
            <tr><td class="label">Nama</td><td class="separator">:</td><td class="value">{{ $second['name'] ?? '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td class="value">{{ $second['position'] ?? '-' }}</td></tr>
        </table>
        <div class="party-role">Selaku atasan PIHAK PERTAMA, selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong>.</div>
        <p>PIHAK PERTAMA berjanji akan mewujudkan target kinerja yang seharusnya, sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah seperti yang telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.</p>
        <p>PIHAK KEDUA akan melakukan supervisi yang diperlukan serta melakukan evaluasi terhadap capaian kinerja dari perjanjian ini dan mengambil tindakan yang diperlukan dalam rangka pemberian penghargaan dan sanksi.</p>
    @endif

    <div class="signature-date">{{ $document['place_date'] ?? 'Banjarnegara, ....................' }}</div>
    <div class="signatures">
        @if($second)
            <div class="signature"><div>Pihak Kedua,</div><strong>{{ $second['position'] }}</strong><div class="space"></div><div class="name">{{ $second['name'] }}</div>@if(filled($second['nip'] ?? null))<div>NIP. {{ $second['nip'] }}</div>@endif</div>
        @endif
        <div class="signature"><div>{{ $second ? 'Pihak Pertama,' : '' }}</div><strong>{{ $first['position'] ?? '-' }}</strong><div class="space"></div><div class="name">{{ $first['name'] ?? '-' }}</div>@if(filled($first['nip'] ?? null))<div>NIP. {{ $first['nip'] }}</div>@endif</div>
    </div>
</section>

<section class="sheet page-break">
    <div class="attachment-heading">
        <strong>LAMPIRAN PERJANJIAN KINERJA TAHUN {{ $document['year'] ?? '' }}</strong>
        <strong>{{ $first['position'] ?? ($document['office_name'] ?? '') }}</strong>
    </div>

    <table class="matrix">
        <thead><tr><th class="number">No</th><th class="performance">Tujuan dan Sasaran Strategis</th><th class="indicator">Indikator Kinerja</th><th class="target">Target</th></tr></thead>
        <tbody>
        @forelse($groups as $group)
            @foreach($group['indicators'] as $indicatorIndex => $indicator)
                <tr>
                    @if($indicatorIndex === 0)
                        <td class="number" rowspan="{{ count($group['indicators']) }}">{{ $group['number'] ?? '' }}</td>
                        <td class="performance" rowspan="{{ count($group['indicators']) }}"><span class="group-label">{{ $group['type_label'] }}</span>{{ $group['performance'] }}</td>
                    @endif
                    <td class="indicator">{{ $indicator['name'] }}</td>
                    <td class="target">{{ $indicator['target'] }}{{ ($indicator['unit'] ?? '-') !== '-' ? ' '.$indicator['unit'] : '' }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="4" style="text-align:center">Belum ada matriks kinerja.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="matrix program-table">
        <thead><tr><th>Program</th><th class="money">Anggaran</th><th class="note">Keterangan</th></tr></thead>
        <tbody>
        @forelse($programs as $program)
            <tr><td>{{ filled($program['code'] ?? null) ? $program['code'].' - ' : '' }}{{ $program['name'] }}</td><td class="money">{{ $program['budget_label'] }}</td><td class="note">{{ $program['note'] }}</td></tr>
        @empty
            <tr><td>Belum ada program.</td><td class="money">Rp 0</td><td class="note">-</td></tr>
        @endforelse
        <tr class="total-row"><td>Total Anggaran</td><td class="money">{{ $document['total_budget_label'] ?? 'Rp 0' }}</td><td></td></tr>
        </tbody>
    </table>
    <div class="source">Sumber data: {{ $document['source_label'] ?? '-' }}</div>

    <div class="signature-date">{{ $document['place_date'] ?? 'Banjarnegara, ....................' }}</div>
    <div class="signatures">
        @if($second)
            <div class="signature"><strong>{{ $second['position'] }}</strong><div class="space"></div><div class="name">{{ $second['name'] }}</div>@if(filled($second['nip'] ?? null))<div>NIP. {{ $second['nip'] }}</div>@endif</div>
        @endif
        <div class="signature"><strong>{{ $first['position'] ?? '-' }}</strong><div class="space"></div><div class="name">{{ $first['name'] ?? '-' }}</div>@if(filled($first['nip'] ?? null))<div>NIP. {{ $first['nip'] }}</div>@endif</div>
    </div>
</section>
</body>
</html>
