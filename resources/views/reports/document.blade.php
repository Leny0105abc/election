<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · {{ $election->name }}</title>
    <style>
        @page { margin: 18mm 14mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10.5px; color: #1e293b; margin: 0; }
        .wrap { max-width: {{ $mode === 'print' ? '1000px' : 'none' }}; margin: 0 auto; padding: {{ $mode === 'print' ? '24px' : '0' }}; }
        .header { width: 100%; border-bottom: 3px solid {{ $school->primary_color }}; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { vertical-align: middle; }
        .logo { width: 62px; height: 62px; object-fit: contain; }
        .school { font-size: 15px; font-weight: bold; margin: 0; }
        .muted { color: #64748b; }
        h1 { font-size: 15px; margin: 6px 0 2px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { font-size: 12px; margin: 14px 0 3px; color: {{ $school->primary_color }}; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; page-break-inside: auto; }
        table.data th { background: #f1f5f9; text-align: left; padding: 5px 6px; border: 1px solid #cbd5e1; font-size: 9.5px; text-transform: uppercase; }
        table.data td { padding: 4px 6px; border: 1px solid #e2e8f0; }
        table.data tr { page-break-inside: avoid; }
        tr.win td { background: #ecfdf5; font-weight: bold; }
        table.summary { border-collapse: collapse; margin: 6px 0 4px; }
        table.summary td { padding: 2px 14px 2px 0; }
        .note { font-size: 9.5px; color: #475569; margin: 2px 0; }
        .sigs { width: 100%; margin-top: 36px; page-break-inside: avoid; }
        .sigs td { width: 33%; padding: 0 10px; vertical-align: bottom; text-align: center; }
        .sigline { border-top: 1px solid #0f172a; padding-top: 3px; font-weight: bold; margin-top: 34px; min-height: 14px; }
        .footer { margin-top: 18px; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { font: inherit; padding: 8px 16px; border-radius: 8px; border: 0; background: {{ $school->primary_color }}; color: #fff; cursor: pointer; font-size: 13px; }
        @media print { .toolbar { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
<div class="wrap">
    @if ($mode === 'print')
        <div class="toolbar"><button type="button" onclick="window.print()">Print this report</button></div>
    @endif

    <table class="header">
        <tr>
            @if ($logo)
                <td style="width: 72px;"><img src="{{ $logo }}" alt="" class="logo"></td>
            @endif
            <td>
                <p class="school">{{ $school->name }}</p>
                <div class="muted">
                    {{ collect([$school->address, $school->division, $school->region])->filter()->implode(' · ') }}
                    @if ($school->school_code) · School ID {{ $school->school_code }} @endif
                </div>
                <h1>{{ $report['title'] }}</h1>
                <div><strong>{{ $election->name }}</strong> · S.Y. {{ $election->schoolYear->name }}</div>
                <div class="muted">Voting period: {{ local_dt($election->starts_at) }} – {{ local_dt($election->ends_at) }} (Philippine time) · Status: {{ $election->status->label() }}</div>
            </td>
        </tr>
    </table>

    @if ($report['summary'])
        <table class="summary">
            @foreach (collect($report['summary'])->chunk(2) as $pair)
                <tr>
                    @foreach ($pair as $label => $value)
                        <td class="muted">{{ $label }}:</td><td><strong>{{ $value }}</strong></td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($report['sections'] as $section)
        @if (! empty($section['heading']))<h2>{{ $section['heading'] }}</h2>@endif
        @if (! empty($section['note']))<p class="note">{{ $section['note'] }}</p>@endif
        <table class="data">
            <thead><tr>@foreach ($section['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($section['rows'] as $i => $row)
                    <tr class="{{ in_array($i, $section['highlight'] ?? [], true) ? 'win' : '' }}">
                        @foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['headings']) }}" class="muted">No records.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    @foreach ($report['notes'] as $note)
        <p class="note">{{ $note }}</p>
    @endforeach

    <table class="sigs">
        <tr>
            @foreach ($signatories as $sig)
                <td>
                    <div class="muted" style="text-align:left">{{ $sig['label'] }}:</div>
                    <div class="sigline">{{ $sig['name'] ?: ' ' }}</div>
                    <div class="muted">{{ $sig['title'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="footer">
        Generated by SchoolVote on {{ $generatedAt }} (Asia/Manila) by {{ $generatedBy }}. Ballot secrecy: this document contains no individual ballot choices.
    </div>
</div>
</body>
</html>
