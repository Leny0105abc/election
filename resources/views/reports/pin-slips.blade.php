<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student PIN slips</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #0f172a; margin: 0; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { width: 50%; padding: 4px; vertical-align: top; }
        .slip { border: 1px dashed #64748b; padding: 8px 10px; height: 118px; page-break-inside: avoid; }
        .school { font-weight: bold; font-size: 10.5px; color: {{ $school->primary_color }}; }
        .name { font-size: 11.5px; font-weight: bold; margin-top: 4px; }
        .pin { font-family: DejaVu Sans Mono, monospace; font-size: 20px; font-weight: bold; letter-spacing: 4px; margin: 4px 0; }
        .muted { color: #475569; font-size: 8.5px; }
    </style>
</head>
<body>
    <table class="grid">
        @foreach (array_chunk($issued, 2) as $pair)
            <tr>
                @foreach ($pair as $item)
                    @php($enr = $item['student']->enrollments->first())
                    <td>
                        <div class="slip">
                            <div class="school">{{ $school->name }} · SchoolVote</div>
                            <div class="name">{{ $item['student']->sortable_name }}</div>
                            <div class="muted">{{ $enr ? 'Grade '.$enr->grade_level.' · '.$enr->section : '' }} · Student ID: {{ $item['student']->student_number }}</div>
                            <div class="pin">PIN: {{ $item['pin'] }}</div>
                            <div class="muted">Sign in at {{ $loginUrl }} with your Student ID (or LRN) and this PIN.
                                Keep it secret. Do not share it. Issued {{ $generatedAt }}.</div>
                        </div>
                    </td>
                @endforeach
                @if (count($pair) === 1)<td></td>@endif
            </tr>
        @endforeach
    </table>
</body>
</html>
