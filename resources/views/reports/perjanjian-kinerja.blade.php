@php
    $document = data_get($report, 'metadata.pk_document', []);
    $logoPath = public_path('images/logo-banjarnegara.png');
    $logo = $document['logo_data_uri'] ?? (is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null);
    $first = $document['first_party'] ?? [];
    $second = $document['second_party'] ?? null;
    $groups = $document['performance_groups'] ?? [];
    $programs = $document['programs'] ?? [];
    $activityBudgetGroups = $document['activity_budget_groups'] ?? [];
    $isHeadOfOpd = ($document['level'] ?? null) === 'kepala_opd';
    $isStructural = ($document['level'] ?? null) === 'struktural';
    $isLowerCascading = (bool) ($document['is_lower_cascading'] ?? false);
    $isManualIndividual = (bool) ($document['is_manual_individual'] ?? false);
    $usesActivityFormat = $isLowerCascading || $isManualIndividual;
    $usesOfficialOpdFormat = $isHeadOfOpd || $isStructural || $usesActivityFormat;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] ?? 'Perjanjian Kinerja' }}</title>
    <style>
        @page { margin: 13mm 18mm 14mm; size: 210mm 330mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.5; }
        .sheet { min-height: 303mm; position: relative; }
        .page-break { font-size: 11pt; line-height: 1.5; page-break-before: always; }
        .letterhead { border-bottom: 2px solid #111; min-height: 27mm; padding: 0 4mm 5px; position: relative; width: 100%; }
        .letterhead-logo { left: 7mm; position: absolute; text-align: center; top: 0; width: 20mm; }
        .letterhead-logo img { display: block; height: 24mm; margin: auto; width: auto; }
        .letterhead-copy { padding: 0 4mm; text-align: center; }
        .letterhead-copy .government { font-size: 15pt; line-height: 1.15; }
        .letterhead-copy .office { font-size: 18pt; font-weight: 700; line-height: 1.12; text-transform: uppercase; }
        .letterhead-copy .address { font-size: 10pt; line-height: 1.15; margin-top: 2px; }
        .letterhead-copy .city { font-size: 11pt; line-height: 1.2; text-transform: uppercase; }
        h1 { font-size: 16pt; font-weight: 700; margin: 19px 0 17px; text-align: center; }
        p { line-height: 1.5; margin: 0 0 8px; text-align: justify; }
        .party-table { border-collapse: collapse; margin: 3px 0 5px; width: 100%; }
        .party-table td { border: 0; padding: 1px 5px; vertical-align: top; }
        .party-table .label { width: 18mm; }
        .party-table .separator { padding-left: 0; padding-right: 0; width: 4mm; }
        .party-role { font-size: 12pt; line-height: 1.5; margin: 0 0 7px; }
        .signature-date { margin: 12px 0 2px 50%; text-align: center; width: 50%; }
        .signatures { display: table; width: 100%; }
        .signature { display: table-cell; text-align: center; vertical-align: top; width: 50%; }
        .signature .position { display: block; min-height: 27px; text-transform: uppercase; }
        .signature .space { height: 52px; }
        .signature .name { font-weight: 700; text-decoration: underline; }
        .signature .rank, .signature .nip { font-size: inherit; line-height: 1.5; }
        .attachment-heading { line-height: 1.5; margin: 1mm 0 12px; text-align: center; }
        .attachment-heading strong { display: block; font-size: 11pt; }
        .attachment-identity { font-size: 11pt; line-height: 1.5; margin: 0 0 12px; width: 100%; }
        .attachment-identity td { border: 0; padding: 2px 0; vertical-align: top; }
        .attachment-identity .label { font-weight: 700; width: 31mm; }
        .attachment-identity .separator { width: 5mm; }
        table.matrix { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .matrix th, .matrix td { border: 1px solid #111; padding: 3px 5px; vertical-align: top; }
        .matrix th { background: #fff; font-size: 11pt; line-height: 1.5; text-align: center; text-transform: uppercase; }
        .matrix td { font-size: 11pt; line-height: 1.5; }
        .matrix .number { text-align: center; width: 7%; }
        .matrix .performance { width: 46%; }
        .matrix .indicator { width: 33%; }
        .matrix .target { text-align: center; width: 14%; }
        .matrix .program-name { width: 38%; }
        .matrix .money { text-align: left; white-space: nowrap; width: 33%; }
        .matrix .note { text-align: left; width: 29%; }
        .group-label { color: #444; display: block; font-size: 11pt; font-weight: 700; letter-spacing: .05em; line-height: 1.5; margin-bottom: 2px; text-transform: uppercase; }
        .program-table { margin-top: 18px; }
        .program-number { display: inline-block; width: 5mm; }
        .activity-list { margin: 4px 0 0 5mm; }
        .activity-item { display: table; margin-top: 2px; width: calc(100% - 5mm); }
        .activity-letter { display: table-cell; width: 6mm; }
        .activity-name { display: table-cell; }
        .lower-budget-table .activity-main--with-children td { border-bottom: 0; }
        .lower-budget-table .subactivity-row td { border-bottom: 0; border-top: 0; padding-bottom: 2px; padding-top: 2px; }
        .lower-budget-table .subactivity-row--last td { border-bottom: 1px solid #111; padding-bottom: 4px; }
        .lower-budget-table .subactivity-name { padding-left: 10mm; }
        .lower-budget-table .subactivity-letter { display: inline-block; width: 6mm; }
        .total-row td { background: #f1f1f1; font-weight: 700; }
        .source { color: #555; font-size: 11pt; line-height: 1.5; margin-top: 7px; text-align: left; }
        .official-note { font-size: 11pt; font-style: italic; line-height: 1.5; margin-top: 18px; text-align: left; }
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
            <div class="government">{{ $document['agency_name'] ?? 'PEMERINTAH KABUPATEN BANJARNEGARA' }}</div>
            <div class="office">{{ $document['office_name'] ?? 'PERANGKAT DAERAH' }}</div>
            <div class="address">
                {{ $document['address'] ?? 'Kabupaten Banjarnegara' }}
                @if(filled($document['telephone'] ?? null)) Telepon {{ $document['telephone'] }}@endif
                @if(filled($document['fax'] ?? null)) Faksimile {{ $document['fax'] }}@endif
                @if(filled($document['website'] ?? null) || filled($document['email'] ?? null))
                    <br>@if(filled($document['website'] ?? null))Website {{ $document['website'] }}@endif
                    @if(filled($document['email'] ?? null)){{ filled($document['website'] ?? null) ? ' · ' : '' }}Surel {{ $document['email'] }}@endif
                @endif
            </div>
            <div class="city">{{ $document['city'] ?? 'BANJARNEGARA' }}{{ filled($document['postal_code'] ?? null) ? ' '.$document['postal_code'] : '' }}</div>
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
        <p>Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan dan akuntabel serta berorientasi pada hasil, kami yang bertanda tangan di bawah ini:</p>
        <table class="party-table">
            <tr><td class="label">Nama</td><td class="separator">:</td><td class="value">{{ $first['name'] ?? '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td class="value">{{ $first['position'] ?? '-' }}</td></tr>
        </table>
        <div class="party-role">Selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong></div>
        <table class="party-table">
            <tr><td class="label">Nama</td><td class="separator">:</td><td class="value">{{ $second['name'] ?? '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td class="value">{{ $second['position'] ?? '-' }}</td></tr>
        </table>
        <div class="party-role">Selaku atasan PIHAK PERTAMA, selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong></div>
        <p>PIHAK PERTAMA berjanji akan mewujudkan target kinerja yang seharusnya, sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah seperti yang telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.</p>
        <p>PIHAK KEDUA akan melakukan supervisi yang diperlukan serta melakukan evaluasi terhadap capaian kinerja dari perjanjian ini dan mengambil tindakan yang diperlukan dalam rangka pemberian penghargaan dan sanksi.</p>
    @endif

    <div class="signature-date">{{ $document['place_date'] ?? 'Banjarnegara, ....................' }}</div>
    <div class="signatures">
        @if($second)
            <div class="signature"><div>Pihak Kedua,</div><strong class="position">{{ $second['position'] }}</strong><div class="space"></div><div class="name">{{ $second['name'] }}</div>@if(filled($second['rank'] ?? null))<div class="rank">{{ $second['rank'] }}</div>@endif @if(filled($second['nip'] ?? null))<div class="nip">NIP. {{ $second['nip'] }}</div>@endif</div>
        @endif
        <div class="signature"><div>{{ $second ? 'Pihak Pertama,' : '' }}</div><strong class="position">{{ $first['position'] ?? '-' }}</strong><div class="space"></div><div class="name">{{ $first['name'] ?? '-' }}</div>@if(filled($first['rank'] ?? null))<div class="rank">{{ $first['rank'] }}</div>@endif @if(filled($first['nip'] ?? null))<div class="nip">NIP. {{ $first['nip'] }}</div>@endif</div>
    </div>
</section>

<section class="sheet page-break">
    <div class="attachment-heading">
        <strong>LAMPIRAN PERJANJIAN KINERJA TAHUN {{ $document['year'] ?? '' }}</strong>
        <strong>{{ $usesOfficialOpdFormat ? ($document['office_name'] ?? '') : ($first['position'] ?? ($document['office_name'] ?? '')) }}</strong>
    </div>

    @if($isStructural || $usesActivityFormat)
        <table class="attachment-identity">
            <tr><td class="label">Nama Pejabat</td><td class="separator">:</td><td>{{ $document['employee_name'] ?? ($first['name'] ?? '-') }}</td></tr>
            <tr><td class="label">Unit Kerja</td><td class="separator">:</td><td>{{ $document['work_unit'] ?? ($document['office_name'] ?? '-') }}</td></tr>
        </table>
    @endif

    <table class="matrix">
        <thead><tr><th class="number">No</th><th class="performance">{{ $isManualIndividual ? 'Sasaran Kegiatan dan Sasaran Sub Kegiatan' : ($isLowerCascading ? 'Sasaran Kegiatan dan Sasaran Sub Kegiatan ***' : ($isStructural ? 'Sasaran Program dan Sasaran Kegiatan **' : 'Tujuan dan Sasaran Strategis'.($isHeadOfOpd ? ' *' : ''))) }}</th><th class="indicator">Indikator Kinerja</th><th class="target">Target</th></tr></thead>
        <tbody>
        @forelse($groups as $group)
            @foreach($group['indicators'] as $indicatorIndex => $indicator)
                <tr>
                    @if($indicatorIndex === 0)
                        <td class="number" rowspan="{{ count($group['indicators']) }}">{{ $group['number'] ?? '' }}</td>
                        <td class="performance" rowspan="{{ count($group['indicators']) }}">@unless($usesOfficialOpdFormat)<span class="group-label">{{ $group['type_label'] }}</span>@endunless{{ $group['performance'] }}</td>
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

    @if($usesActivityFormat)
        <table class="matrix program-table lower-budget-table">
            <thead><tr><th class="program-name">Kegiatan dan Sub Kegiatan</th><th class="money">Anggaran</th><th class="note">Keterangan</th></tr></thead>
            <tbody>
            @forelse($activityBudgetGroups as $activity)
                @php($subActivities = $activity['sub_activities'] ?? [])
                <tr class="activity-main {{ count($subActivities) ? 'activity-main--with-children' : '' }}">
                    <td><span class="program-number">{{ $loop->iteration }}.</span>{{ $activity['name'] }}</td>
                    <td class="money">{{ $activity['budget_label'] }}</td>
                    <td class="note" rowspan="{{ max(1, count($subActivities) + 1) }}">{{ $activity['note'] }}</td>
                </tr>
                @foreach($subActivities as $subActivity)
                    <tr class="subactivity-row {{ $loop->last ? 'subactivity-row--last' : '' }}">
                        <td class="subactivity-name"><span class="subactivity-letter">{{ chr(96 + $loop->iteration) }}.</span>{{ $subActivity['name'] }}</td>
                        <td class="money">{{ $subActivity['budget_label'] }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td>Belum ada kegiatan atau sub kegiatan.</td><td class="money">Rp 0</td><td class="note">-</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        <table class="matrix program-table">
            <thead><tr><th class="program-name">Program</th><th class="money">Anggaran</th><th class="note">Keterangan</th></tr></thead>
            <tbody>
            @forelse($programs as $program)
                <tr>
                    <td>
                        <span class="program-number">{{ $loop->iteration }}.</span>{{ ! $usesOfficialOpdFormat && filled($program['code'] ?? null) ? $program['code'].' - ' : '' }}{{ $program['name'] }}
                        @if($isStructural && ! empty($program['activities']))
                            <div class="activity-list">
                                @foreach($program['activities'] as $activity)
                                    <div class="activity-item">
                                        <span class="activity-letter">{{ chr(96 + $loop->iteration) }}.</span>
                                        <span class="activity-name">{{ $activity['name'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="money">{{ $program['budget_label'] }}</td>
                    <td class="note">{{ $program['note'] }}</td>
                </tr>
            @empty
                <tr><td>Belum ada program.</td><td class="money">Rp 0</td><td class="note">-</td></tr>
            @endforelse
            @unless($usesOfficialOpdFormat)<tr class="total-row"><td>Total Anggaran</td><td class="money">{{ $document['total_budget_label'] ?? 'Rp 0' }}</td><td></td></tr>@endunless
            </tbody>
        </table>
    @endif
    @unless($usesOfficialOpdFormat)<div class="source">Sumber data: {{ $document['source_label'] ?? '-' }}</div>@endunless

    <div class="signature-date">{{ $document['place_date'] ?? 'Banjarnegara, ....................' }}</div>
    <div class="signatures">
        @if($second)
            <div class="signature">@if($isStructural || $usesActivityFormat)<div>Pihak Kedua</div>@endif<strong class="position">{{ $second['position'] }}</strong><div class="space"></div><div class="name">{{ $second['name'] }}</div>@if(filled($second['rank'] ?? null))<div class="rank">{{ $second['rank'] }}</div>@endif @if(filled($second['nip'] ?? null))<div class="nip">NIP. {{ $second['nip'] }}</div>@endif</div>
        @endif
        <div class="signature">@if($isStructural || $usesActivityFormat)<div>Pihak Pertama</div>@endif<strong class="position">{{ $first['position'] ?? '-' }}</strong><div class="space"></div><div class="name">{{ $first['name'] ?? '-' }}</div>@if(filled($first['rank'] ?? null))<div class="rank">{{ $first['rank'] }}</div>@endif @if(filled($first['nip'] ?? null))<div class="nip">NIP. {{ $first['nip'] }}</div>@endif</div>
    </div>
    @if($isStructural)
        <div class="official-note">**) Untuk disesuaikan dengan kondisi pada masing-masing Perangkat Daerah; apabila tidak melaksanakan kegiatan, maka diisi sampai ke sasaran program.</div>
    @elseif($isManualIndividual)
        <div class="official-note">***) Untuk kolom kedua disesuaikan dengan kondisi yang dilaksanakan oleh pejabat pengawas pada masing-masing Perangkat Daerah (misalnya hanya melaksanakan sub kegiatan maka diisi hanya sasaran sub kegiatan, demikian juga indikatornya menyesuaikan).</div>
    @elseif($usesActivityFormat)
        <div class="official-note">***) Kolom kedua disesuaikan dengan kondisi yang dilaksanakan oleh pejabat pengawas pada masing-masing Perangkat Daerah. Apabila hanya melaksanakan sub kegiatan, maka diisi hanya sasaran sub kegiatan; demikian juga indikatornya menyesuaikan.</div>
    @endif
</section>
</body>
</html>
