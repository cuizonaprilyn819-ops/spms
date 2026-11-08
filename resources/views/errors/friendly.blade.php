<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Please try again · SLSU SPMS</title>
    <x-workspace-font />
    <style>
        body { margin: 0; padding: 32px 20px; background: #f3f7fc; color: #1e2a4a; font: 16px/1.6 Figtree, sans-serif; }
        main { max-width: 480px; margin: 8vh auto; padding: 28px; border: 1px solid #dce5f0; border-radius: 12px; background: white; }
        h1 { font-size: 26px; line-height: 1.3; }
        a { display: inline-block; margin-top: 12px; padding: 12px 18px; border-radius: 8px; background: #34508e; color: white; font-weight: 600; text-decoration: none; }
        a:focus-visible { outline: 3px solid #0369a1; outline-offset: 4px; }
    </style>
</head>
<body><main>
    <p><strong>SLSU SPMS</strong></p>
    <h1>We couldn’t complete that</h1>
    <p>{{ $message }}</p>
    <p>If you were filling out a form, use your browser’s Back button to copy your entries before refreshing. Check whether your last action was saved before submitting it again.</p>
    <a href="{{ url('/') }}">Return to workspace</a>
</main></body>
</html>
