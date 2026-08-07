<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #132238; font-size: 10px; }
        h1 { margin: 0; font-size: 20px; }
        p { margin: 4px 0 16px; color: #5f6b7a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th { background: #f2f4f7; color: #132238; font-size: 9px; text-transform: uppercase; }
        th, td { border: 1px solid #d8dde6; padding: 6px; text-align: left; vertical-align: top; }
        .page-break { page-break-before: always; }
        .center { text-align: center; }
        .form-title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-top: 18px; }
        .commitment { font-size: 10px; line-height: 1.45; margin: 8px 0 14px; color: #132238; }
        .signature td { height: 48px; }
        .scale { margin: 8px 0 10px; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Generated {{ $generatedAt->format('F d, Y h:i A') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No records available for this report.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($forms as $form)
        <div class="page-break"></div>
        <div class="center form-title">{{ $form['heading'] }}</div>
        <p class="commitment">{{ $form['commitment'] }}</p>

        <table class="signature">
            <tr>
                <th>Prepared By</th>
                @if ($form['reviewed_by'])
                    <th>Reviewed By</th>
                @endif
                <th>Approved By</th>
                <th>Date</th>
            </tr>
            <tr>
                <td>
                    <strong>{{ $form['prepared_name'] }}</strong><br>
                    {{ $form['prepared_position'] }}
                </td>
                @if ($form['reviewed_by'])
                    <td>
                        <strong>{{ $form['reviewed_by'] }}</strong><br>
                        Reviewer
                    </td>
                @endif
                <td>
                    <strong>{{ $form['approved_by'] }}</strong><br>
                    {{ $form['approved_position'] }}
                </td>
                <td></td>
            </tr>
        </table>

        <div class="scale">
            <strong>Rating Scale:</strong>
            {{ implode(' | ', $form['rating_scale']) }}
        </div>

        <table>
            <thead>
                <tr>
                    @foreach ($formHeaders as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($form['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($formHeaders) }}">No performance targets encoded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
