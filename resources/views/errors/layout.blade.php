{{-- Shared Thai error page. Plain inline CSS so it renders even when the app is failing. --}}
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · ระบบทรัพย์สิน</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --bg: #f8fafc; --card: #fff; --text: #0f172a; --muted: #475569; --line: #e2e8f0; --primary: #2563eb; --primary-dark: #1d4ed8; --grid: rgba(15, 23, 42, .045); }
        @media (prefers-color-scheme: dark) { :root { --bg: #020617; --card: #0f172a; --text: #f1f5f9; --muted: #94a3b8; --line: #1e293b; --grid: rgba(255, 255, 255, .04); } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; color: var(--text);
            font: 16px/1.6 "IBM Plex Sans Thai", system-ui, sans-serif; background: var(--bg);
            background-image: radial-gradient(600px 360px at 85% 0%, rgba(37, 99, 235, .14), transparent 70%),
                linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: auto, 32px 32px, 32px 32px; }
        main { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 36px 32px; text-align: center;
            box-shadow: 0 30px 60px -30px rgba(29, 78, 216, .35); }
        .mark { width: 44px; height: 44px; margin: 0 auto 18px; border-radius: 12px; display: grid; place-items: center; color: #fff;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .mark svg { width: 24px; height: 24px; }
        .code { font: 600 13px/1 ui-monospace, monospace; letter-spacing: .12em; color: var(--primary); }
        h1 { font-size: 22px; margin: 10px 0 8px; }
        p { margin: 0 0 24px; color: var(--muted); }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a, button { font: inherit; font-weight: 500; text-decoration: none; padding: 10px 18px; border-radius: 10px; min-height: 44px; cursor: pointer; }
        .primary { color: #fff; background: var(--primary); border: 0; }
        .primary:hover { background: var(--primary-dark); }
        .ghost { color: var(--text); background: transparent; border: 1px solid var(--line); }
        a:focus-visible, button:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
        </div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
            <a class="primary" href="/admin">กลับหน้าแรก</a>
        </div>
    </main>
</body>
</html>
