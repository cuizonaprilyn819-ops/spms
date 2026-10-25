<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $role ? $role.' Ratings' : 'Employee and Supervisor Ratings' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #132238; font-size: 9px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 22px; }
        p { color: #5f6b7a; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8dde6; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f2f4f7; }
    </style>
</head>
<body>
    <h1>{{ $role ? $role.' Ratings' : 'Employee and Supervisor Ratings' }}</h1>
    <p>Generated {{ now()->format('F d, Y h:i A') }}</p>

    <h2>Target evaluations</h2>
    <table>
        <thead><tr><th>Personnel</th><th>Office</th><th>Target</th><th>Q</th><th>E</th><th>T</th><th>Average</th><th>Weighted</th><th>Rated by</th></tr></thead>
        <tbody>
            @forelse ($evaluations as $evaluation)
                <tr>
                    <td>{{ $evaluation->employee?->name }}</td>
                    <td>{{ $evaluation->employee?->office?->name ?? 'No office' }}</td>
                    <td>{{ $evaluation->performanceTarget?->target ?? 'Target unavailable' }}</td>
                    <td>{{ number_format((float) ($evaluation->quality_rating ?? $evaluation->rating), 2) }}</td>
                    <td>{{ number_format((float) ($evaluation->efficiency_rating ?? $evaluation->rating), 2) }}</td>
                    <td>{{ number_format((float) ($evaluation->timeliness_rating ?? $evaluation->rating), 2) }}</td>
                    <td>{{ number_format((float) $evaluation->rating, 2) }}</td>
                    <td>{{ number_format((float) $evaluation->computed_rating, 2) }}</td>
                    <td>{{ $evaluation->ratedBy?->name ?? 'Unknown' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No target evaluations found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Rated IPCR reviews</h2>
    <table>
        <thead><tr><th>Personnel</th><th>Office</th><th>IPCR</th><th>Q</th><th>E</th><th>T</th><th>Average</th><th>Reviewed by</th></tr></thead>
        <tbody>
            @forelse ($ipcrRatings as $ipcr)
                <tr>
                    <td>{{ $ipcr->employee?->name }}</td>
                    <td>{{ $ipcr->employee?->office?->name ?? 'No office' }}</td>
                    <td>{{ $ipcr->title }}</td>
                    <td>{{ number_format((float) $ipcr->quality_rating, 2) }}</td>
                    <td>{{ number_format((float) $ipcr->efficiency_rating, 2) }}</td>
                    <td>{{ number_format((float) $ipcr->timeliness_rating, 2) }}</td>
                    <td>{{ number_format((float) $ipcr->average_rating, 2) }}</td>
                    <td>{{ $ipcr->approvedBy?->name ?? 'Unknown' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No rated IPCR reviews found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
