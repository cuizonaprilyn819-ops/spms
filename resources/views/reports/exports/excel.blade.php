<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table>
        <tr>
            <th colspan="{{ count($headers) }}">{{ $title }}</th>
        </tr>
        <tr>
            <td colspan="{{ count($headers) }}">Generated {{ $generatedAt->format('F d, Y h:i A') }}</td>
        </tr>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
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
    </table>

    @foreach ($forms as $form)
        <br>
        <table>
            <tr>
                <th colspan="{{ count($formHeaders) }}">{{ $form['heading'] }}</th>
            </tr>
            <tr>
                <td colspan="{{ count($formHeaders) }}">{{ $form['commitment'] }}</td>
            </tr>
            <tr>
                <td colspan="{{ count($formHeaders) }}">Prepared By: {{ $form['prepared_name'] }} - {{ $form['prepared_position'] }}</td>
            </tr>
            <tr>
                <td colspan="{{ count($formHeaders) }}">Approved By: {{ $form['approved_by'] }} - {{ $form['approved_position'] }}</td>
            </tr>
            <tr>
                @foreach ($formHeaders as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
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
        </table>
    @endforeach
</body>
</html>
