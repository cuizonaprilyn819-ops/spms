<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPMS temporarily unavailable</title>
</head>
<body>
    <main>
        <h1>SPMS temporarily unavailable</h1>
        <p>{{ $message }}</p>
        <p>Your login could not be completed while the database is unavailable.</p>
        <a href="{{ url('/login') }}">Return to sign in</a>
    </main>
</body>
</html>
