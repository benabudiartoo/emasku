<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #14513c; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: #14513c; }
        .printed { color: #6b7280; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e8f3ee; text-align: left; font-weight: bold; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; word-wrap: break-word; white-space: pre-wrap; }
        .facts td { border: 0; padding: 2px 6px 2px 0; }
        .facts td:first-child { width: 28%; color: #4b5563; }
        .empty { color: #6b7280; }
        .notes { margin-top: 16px; color: #4b5563; }
        .notes p { margin: 0 0 4px; }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <p class="printed">Dicetak {{ $printed }}</p>

    @if ($report->facts !== [])
        <table class="facts">
            @foreach ($report->facts as $label => $value)
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    @foreach ($report->sections as $section)
        <h2>{{ $section->heading }}</h2>
        @if ($section->rows === [])
            <p class="empty">{{ $section->emptyText ?? 'Tidak ada data.' }}</p>
        @else
            <table>
                <thead>
                    <tr>@foreach ($section->columns as $column)<th>{{ $column }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($section->rows as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if ($report->notes !== [])
        <div class="notes">
            @foreach ($report->notes as $note)<p>{{ $note }}</p>@endforeach
        </div>
    @endif
</body>
</html>
