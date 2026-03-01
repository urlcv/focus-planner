<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Focus Planner link</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 40px 20px; color: #111827; }
        .card { background: #ffffff; border-radius: 12px; max-width: 480px; margin: 0 auto; padding: 40px; }
        .logo { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 32px; }
        h1 { font-size: 22px; font-weight: 700; margin: 0 0 12px; }
        p { color: #6b7280; font-size: 15px; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px; padding: 14px 28px; border-radius: 8px; }
        .url { margin-top: 24px; padding-top: 24px; border-top: 1px solid #f3f4f6; }
        .url p { font-size: 13px; color: #9ca3af; margin: 0 0 6px; }
        .url a { font-size: 12px; color: #2563eb; word-break: break-all; }
        .footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">URLCV Focus Planner</div>
        <h1>Here's your planner link</h1>
        <p>Click the button below to jump straight back into your Focus Planner — all your tasks and settings will be exactly as you left them.</p>
        <a href="{{ $plannerUrl }}" class="btn">Open my Focus Planner →</a>
        <div class="url">
            <p>Or copy this link into your browser:</p>
            <a href="{{ $plannerUrl }}">{{ $plannerUrl }}</a>
        </div>
    </div>
    <div class="footer">
        You're receiving this because you requested a Focus Planner recovery link.<br>
        <a href="https://urlcv.com" style="color:#9ca3af;">urlcv.com</a>
    </div>
</body>
</html>
