<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#050b1a">
    <meta name="description" content="{{ $bridgeName }} — {{ __('Toll Rates') }}">
    <title>{{ $bridgeName }} · {{ __('Toll Rates') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=hind-siliguri:400,500,600,700|baloo-da-2:600,700,800|space-grotesk:500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050b1a;
            --bg-2: #0a1530;
            --ink: #f1f5f9;
            --muted: #94a3b8;
            --line: rgb(255 255 255 / 0.09);
            --glass: rgb(255 255 255 / 0.045);
            --teal: #2dd4bf;
            --amber: #fbbf24;
            --rose: #fb7185;
            --violet: #a78bfa;
            --font: 'Hind Siliguri', 'Nirmala UI', 'Vrinda', system-ui, sans-serif;
            --display: 'Baloo Da 2', 'Hind Siliguri', 'Nirmala UI', system-ui, sans-serif;
            --latin: 'Space Grotesk', 'Hind Siliguri', system-ui, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; min-height: 100vh; overflow-x: hidden;
            font-family: var(--font); color: var(--ink); background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }

        /* ---------- Animated backdrop ---------- */
        .sky { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: radial-gradient(ellipse at top, var(--bg-2), var(--bg) 65%); }
        .sky::after {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse at 50% 20%, #000 10%, transparent 70%);
            opacity: .5;
        }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .55; animation: drift 22s ease-in-out infinite alternate; }
        .orb--teal { width: 46vw; height: 46vw; background: #0d9488; top: -14vw; left: -10vw; }
        .orb--amber { width: 32vw; height: 32vw; background: #b45309; top: 30vh; right: -10vw; animation-delay: -6s; opacity: .35; }
        .orb--violet { width: 38vw; height: 38vw; background: #6d28d9; bottom: -16vw; left: 30vw; animation-delay: -12s; opacity: .35; }
        @keyframes drift { to { transform: translate3d(6vw, 4vh, 0) scale(1.15); } }

        .wrap { width: min(1200px, 100% - 32px); margin-inline: auto; }

        /* ---------- Nav ---------- */
        .nav { position: sticky; top: 0; z-index: 20; backdrop-filter: blur(14px); background: rgb(5 11 26 / .55); border-bottom: 1px solid var(--line); }
        .nav__inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 0; }
        .brand { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .brand__mark {
            flex: none; display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: .9rem; color: #fff;
            background: linear-gradient(135deg, var(--teal), #0f766e 55%, #134e4a);
            box-shadow: 0 0 0 1px rgb(255 255 255 / .15) inset, 0 10px 30px -8px rgb(45 212 191 / .6);
        }
        .brand__mark svg { width: 1.4rem; height: 1.4rem; }
        .brand__title { display: block; font-weight: 700; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .brand__sub { display: block; font-size: .78rem; color: var(--muted); }
        .nav__actions { display: flex; align-items: center; gap: .5rem; flex: none; }
        .lang { display: flex; padding: .2rem; border-radius: 999px; background: var(--glass); border: 1px solid var(--line); }
        .lang a { padding: .3rem .7rem; border-radius: 999px; font-size: .82rem; font-weight: 600; color: var(--muted); transition: .2s; }
        .lang a.is-active { background: rgb(255 255 255 / .12); color: #fff; }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem; padding: .7rem 1.25rem; border-radius: 999px;
            font-weight: 600; font-size: .95rem; transition: transform .2s, box-shadow .2s, background .2s;
        }
        .btn svg { width: 1.1rem; height: 1.1rem; }
        .btn--primary { color: #04261f; background: linear-gradient(135deg, #5eead4, var(--teal) 45%, #14b8a6); box-shadow: 0 10px 30px -10px rgb(45 212 191 / .8); }
        .btn--primary:hover { transform: translateY(-2px); box-shadow: 0 16px 40px -10px rgb(45 212 191 / .95); }
        .btn--ghost { background: var(--glass); border: 1px solid var(--line); }
        .btn--ghost:hover { background: rgb(255 255 255 / .1); transform: translateY(-2px); }
        .btn--sm { padding: .5rem 1rem; font-size: .85rem; }

        /* ---------- Hero ---------- */
        .hero { padding: clamp(3rem, 8vw, 6rem) 0 2rem; text-align: center; }
        .pill {
            display: inline-flex; align-items: center; gap: .55rem; padding: .4rem 1rem .4rem .5rem; border-radius: 999px;
            background: var(--glass); border: 1px solid var(--line); font-size: .9rem; color: #cbd5e1;
        }
        .pill__dot { position: relative; display: grid; place-items: center; padding: .15rem .55rem; border-radius: 999px; background: rgb(45 212 191 / .15); color: var(--teal); font-weight: 700; font-size: .75rem; letter-spacing: .04em; }
        .pill__dot::before { content: ''; width: .45rem; height: .45rem; margin-right: .35rem; border-radius: 50%; background: var(--teal); box-shadow: 0 0 0 0 rgb(45 212 191 / .7); animation: ping 1.8s infinite; display: inline-block; }
        .pill__dot { display: inline-flex; align-items: center; }
        @keyframes ping { 70% { box-shadow: 0 0 0 .55rem rgb(45 212 191 / 0); } 100% { box-shadow: 0 0 0 0 rgb(45 212 191 / 0); } }

        .hero__title {
            margin: 1.4rem auto .6rem; max-width: 18ch; font-family: var(--display); font-weight: 800;
            font-size: clamp(2.4rem, 7vw, 5.2rem); line-height: 1.05; letter-spacing: -.01em;
            background: linear-gradient(100deg, #fff 10%, #99f6e4 35%, var(--amber) 60%, #fff 85%);
            background-size: 220% auto; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: shine 7s linear infinite;
        }
        @keyframes shine { to { background-position: -220% center; } }
        .hero__lead { margin: 0 auto; max-width: 46ch; color: var(--muted); font-size: clamp(1rem, 2.2vw, 1.2rem); line-height: 1.7; }
        .hero__cta { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; margin-top: 2rem; }

        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; max-width: 760px; margin: 2.75rem auto 0; border-radius: 1.25rem; overflow: hidden; background: var(--line); border: 1px solid var(--line); }
        .stat { padding: 1.1rem .75rem; background: rgb(8 16 36 / .85); }
        .stat__value { font-family: var(--display); font-weight: 800; font-size: clamp(1.4rem, 4vw, 2.1rem); line-height: 1.1; }
        .stat__value small { font-size: .55em; color: var(--muted); margin-left: .15rem; }
        .stat__label { margin-top: .2rem; font-size: .82rem; color: var(--muted); }
        .stat:nth-child(1) .stat__value { color: var(--teal); }
        .stat:nth-child(2) .stat__value { color: var(--amber); }
        .stat:nth-child(3) .stat__value { color: var(--rose); }

        /* ---------- Bridge scene ---------- */
        .scene { position: relative; margin: 3rem auto 0; max-width: 1100px; height: clamp(150px, 22vw, 230px); }
        .scene svg.bridge { position: absolute; inset: 0; width: 100%; height: 100%; }
        .bridge .cable { stroke: url(#cableGrad); stroke-width: 1.4; opacity: .75; }
        .bridge .pylon { fill: url(#pylonGrad); }
        .bridge .light { fill: var(--amber); animation: blink 2.4s ease-in-out infinite; }
        .bridge .light:nth-of-type(2n) { animation-delay: -1.2s; }
        @keyframes blink { 50% { opacity: .2; } }
        .road { position: absolute; left: 0; right: 0; bottom: 22%; height: 2.6rem; overflow: hidden; }
        .car { position: absolute; bottom: .1rem; font-size: clamp(1.4rem, 3vw, 2rem); animation: drive linear infinite; filter: drop-shadow(0 6px 10px rgb(0 0 0 / .5)); }
        .car--rtl { transform: scaleX(1); }
        .car--ltr { transform: scaleX(-1); }
        @keyframes drive { from { left: -8%; } to { left: 108%; } }
        @keyframes drive-back { from { left: 108%; } to { left: -8%; } }
        .water { position: absolute; left: 0; right: 0; bottom: 0; height: 22%; background: linear-gradient(180deg, rgb(45 212 191 / .14), transparent); mask-image: linear-gradient(90deg, transparent, #000 15%, #000 85%, transparent); }
        .water::before { content: ''; position: absolute; inset: 0; background: repeating-linear-gradient(90deg, transparent 0 22px, rgb(153 246 228 / .25) 22px 34px, transparent 34px 60px); height: 2px; top: 30%; animation: waves 6s linear infinite; }
        .water::after { content: ''; position: absolute; inset: 0; background: repeating-linear-gradient(90deg, transparent 0 40px, rgb(153 246 228 / .15) 40px 52px, transparent 52px 90px); height: 2px; top: 65%; animation: waves 9s linear infinite reverse; }
        @keyframes waves { to { background-position: 240px 0; } }

        /* ---------- Rates ---------- */
        .section { padding: clamp(3rem, 7vw, 5rem) 0; }
        .section__head { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 1.25rem; margin-bottom: 2rem; }
        .eyebrow { font-family: var(--latin); text-transform: uppercase; letter-spacing: .18em; font-size: .75rem; font-weight: 700; color: var(--teal); }
        .section__title { margin: .35rem 0 .3rem; font-family: var(--display); font-weight: 800; font-size: clamp(1.9rem, 4.5vw, 3rem); line-height: 1.1; }
        .section__note { margin: 0; color: var(--muted); }
        .search { position: relative; width: min(100%, 320px); }
        .search svg { position: absolute; left: 1rem; top: 50%; width: 1.1rem; height: 1.1rem; transform: translateY(-50%); color: var(--muted); }
        .search input {
            width: 100%; padding: .85rem 1rem .85rem 2.75rem; border-radius: 999px; border: 1px solid var(--line);
            background: var(--glass); color: var(--ink); font: inherit; outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .search input:focus { border-color: rgb(45 212 191 / .6); box-shadow: 0 0 0 4px rgb(45 212 191 / .15); }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.1rem; }
        .card {
            --c: #14b8a6;
            position: relative; isolation: isolate; overflow: hidden; padding: 1.35rem 1.35rem 1.2rem; border-radius: 1.4rem;
            background: linear-gradient(160deg, rgb(255 255 255 / .07), rgb(255 255 255 / .02));
            border: 1px solid var(--line);
            transition: transform .35s cubic-bezier(.2, .8, .2, 1), border-color .35s, box-shadow .35s;
            opacity: 0; transform: translateY(24px);
        }
        .card.is-visible { opacity: 1; transform: none; transition: opacity .6s ease, transform .6s cubic-bezier(.2, .8, .2, 1), border-color .35s, box-shadow .35s; }
        .card::before {
            content: ''; position: absolute; z-index: -1; width: 180px; height: 180px; right: -60px; top: -70px; border-radius: 50%;
            background: var(--c); filter: blur(50px); opacity: .35; transition: opacity .35s, transform .35s;
        }
        .card:hover { transform: translateY(-6px); border-color: color-mix(in srgb, var(--c) 60%, transparent); box-shadow: 0 24px 50px -20px color-mix(in srgb, var(--c) 70%, transparent); }
        .card:hover::before { opacity: .6; transform: scale(1.25); }
        .card__top { display: flex; align-items: flex-start; justify-content: space-between; }
        .card__class {
            display: grid; place-items: center; min-width: 2.3rem; height: 2.3rem; padding: 0 .5rem; border-radius: .75rem;
            font-family: var(--display); font-weight: 800; font-size: 1.15rem; color: #fff;
            background: color-mix(in srgb, var(--c) 35%, transparent); border: 1px solid color-mix(in srgb, var(--c) 55%, transparent);
        }
        .card__icon { font-size: 2.9rem; line-height: 1; filter: drop-shadow(0 8px 14px rgb(0 0 0 / .45)); transition: transform .4s; }
        .card:hover .card__icon { transform: translateX(6px) rotate(-4deg) scale(1.08); }
        .card__name { margin: 1rem 0 .1rem; font-size: 1.2rem; font-weight: 700; line-height: 1.3; }
        .card__alt { font-size: .85rem; color: var(--muted); }
        .card__tag { display: inline-block; margin-top: .55rem; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; color: #cbd5e1; background: rgb(255 255 255 / .06); border: 1px solid var(--line); }
        .card__price { display: flex; align-items: baseline; gap: .3rem; margin-top: 1.1rem; }
        .card__currency { font-size: 1.3rem; font-weight: 700; color: color-mix(in srgb, var(--c) 55%, #fff); }
        .card__amount { font-family: var(--display); font-weight: 800; font-size: 2.6rem; line-height: 1; }
        .card__unit { font-size: .85rem; color: var(--muted); }
        .bar { margin-top: 1rem; height: .4rem; border-radius: 999px; background: rgb(255 255 255 / .07); overflow: hidden; }
        .bar span { display: block; height: 100%; width: 0; border-radius: inherit; background: linear-gradient(90deg, color-mix(in srgb, var(--c) 50%, #fff), var(--c)); box-shadow: 0 0 12px var(--c); transition: width 1.2s cubic-bezier(.2, .8, .2, 1) .2s; }
        .card.is-visible .bar span { width: var(--w); }
        .card--exempt { background: repeating-linear-gradient(135deg, rgb(255 255 255 / .03) 0 12px, transparent 12px 24px), linear-gradient(160deg, rgb(255 255 255 / .06), rgb(255 255 255 / .02)); }
        .card--exempt .card__amount { display: inline-block; padding-block: .3em .15em; margin-block: -.3em -.15em; background: linear-gradient(90deg, var(--teal), #a7f3d0); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .card[hidden] { display: none; }
        .empty { padding: 3rem 1rem; text-align: center; color: var(--muted); border: 1px dashed var(--line); border-radius: 1.4rem; }

        /* ---------- Info strip ---------- */
        .info { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
        .info__item { display: flex; gap: .9rem; align-items: flex-start; padding: 1.25rem; border-radius: 1.2rem; background: var(--glass); border: 1px solid var(--line); }
        .info__icon { flex: none; display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: .8rem; font-size: 1.3rem; background: rgb(255 255 255 / .06); }
        .info__title { font-weight: 700; }
        .info__text { margin-top: .15rem; font-size: .9rem; color: var(--muted); line-height: 1.55; }

        /* ---------- Footer ---------- */
        .footer { border-top: 1px solid var(--line); padding: 2rem 0 2.5rem; }
        .footer__inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; color: var(--muted); font-size: .9rem; }
        .powered { display: inline-flex; align-items: center; gap: .55rem; padding: .55rem 1.1rem; border-radius: 999px; background: var(--glass); border: 1px solid var(--line); font-family: var(--latin); font-size: .85rem; }
        .powered strong {
            font-weight: 700; letter-spacing: .02em;
            background: linear-gradient(90deg, var(--teal), var(--violet), var(--amber), var(--teal));
            background-size: 300% auto; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: shine 6s linear infinite;
        }
        .powered__bolt { color: var(--amber); }
        .clock { font-family: var(--latin); font-variant-numeric: tabular-nums; }

        @media (max-width: 640px) {
            .brand__sub, .nav .btn span { display: none; }
            .nav .btn--sm { padding: .55rem; }
            .stat__label { font-size: .72rem; }
            .footer__inner { justify-content: center; text-align: center; }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
            .card { padding: 1rem .9rem .9rem; border-radius: 1.1rem; }
            .card__class { min-width: 1.9rem; height: 1.9rem; font-size: .95rem; border-radius: .6rem; }
            .card__icon { font-size: 2.1rem; }
            .card__name { margin-top: .75rem; font-size: 1rem; }
            .card__alt { font-size: .78rem; }
            .card__tag { font-size: .68rem; padding: .1rem .5rem; }
            .card__price { margin-top: .8rem; }
            .card__currency { font-size: 1rem; }
            .card__amount { font-size: 1.85rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .card { opacity: 1; transform: none; }
            .card .bar span { width: var(--w); }
            .car { display: none; }
        }
    </style>
</head>
<body>
    <div class="sky" aria-hidden="true">
        <span class="orb orb--teal"></span>
        <span class="orb orb--amber"></span>
        <span class="orb orb--violet"></span>
    </div>

    <header class="nav">
        <div class="wrap nav__inner">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand__mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 21h18" /><path d="M5 21V10l7-5 7 5v11" /><path d="M9 21v-6h6v6" /><path d="M2 10h20" />
                    </svg>
                </span>
                <span style="min-width: 0">
                    <span class="brand__title">{{ $bridgeName }}</span>
                    <span class="brand__sub">{{ __('Toll Management System') }}</span>
                </span>
            </a>
            <nav class="nav__actions">
                <div class="lang" role="group" aria-label="Language">
                    <a href="{{ route('locale.switch', 'bn') }}" @class(['is-active' => $isBangla])>বাং</a>
                    <a href="{{ route('locale.switch', 'en') }}" @class(['is-active' => ! $isBangla])>EN</a>
                </div>
                <a href="{{ url('/admin') }}" class="btn btn--primary btn--sm" aria-label="{{ __('Sign in') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="m10 17 5-5-5-5" /><path d="M15 12H3" /></svg>
                    <span>{{ __('Sign in') }}</span>
                </a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap">
                <span class="pill">
                    <span class="pill__dot">LIVE</span>
                    {{ __('Under Kushtia Roads Division') }}
                </span>
                <h1 class="hero__title">{{ $bridgeName }}</h1>
                <p class="hero__lead">{{ __('Official toll rates in force today. Loaded and empty vehicles pay the same rate — fast, transparent and fully digital toll collection.') }}</p>
                <div class="hero__cta">
                    <a href="#rates" class="btn btn--primary">
                        {{ __('View toll rates') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14" /><path d="m19 12-7 7-7-7" /></svg>
                    </a>
                    <a href="{{ route('mobile.app') }}" class="btn btn--ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2" /><path d="M11 18h2" /></svg>
                        {{ __('Mobile collection app') }}
                    </a>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat__value">{{ $categoryCount }}</div>
                        <div class="stat__label">{{ __('Vehicle classes') }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat__value">{{ $currency }}{{ $lowestAmount }}</div>
                        <div class="stat__label">{{ __('Lowest toll') }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat__value">{{ $currency }}{{ $highestAmount }}</div>
                        <div class="stat__label">{{ __('Highest toll') }}</div>
                    </div>
                </div>
            </div>

            <div class="scene" aria-hidden="true">
                <svg class="bridge" viewBox="0 0 1100 230" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="cableGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#5eead4" />
                            <stop offset="1" stop-color="#5eead4" stop-opacity=".1" />
                        </linearGradient>
                        <linearGradient id="pylonGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#e2e8f0" />
                            <stop offset="1" stop-color="#334155" />
                        </linearGradient>
                        <linearGradient id="deckGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#2dd4bf" stop-opacity="0" />
                            <stop offset=".15" stop-color="#2dd4bf" />
                            <stop offset=".5" stop-color="#fbbf24" />
                            <stop offset=".85" stop-color="#2dd4bf" />
                            <stop offset="1" stop-color="#2dd4bf" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    @foreach ([300, 800] as $pylonX)
                        @foreach ([-230, -180, -130, -80, -40, 40, 80, 130, 180, 230] as $offset)
                            <line class="cable" x1="{{ $pylonX }}" y1="{{ 20 + abs($offset) / 10 }}" x2="{{ $pylonX + $offset }}" y2="150" />
                        @endforeach
                        <path class="pylon" d="M{{ $pylonX - 9 }} 180 L{{ $pylonX - 4 }} 8 L{{ $pylonX + 4 }} 8 L{{ $pylonX + 9 }} 180 Z" />
                        <circle class="light" cx="{{ $pylonX }}" cy="6" r="3.5" />
                    @endforeach
                    <rect x="0" y="148" width="1100" height="6" rx="3" fill="url(#deckGrad)" />
                    <rect x="0" y="156" width="1100" height="2" fill="#0f172a" opacity=".8" />
                    @foreach ([120, 300, 480, 620, 800, 980] as $pierX)
                        <rect x="{{ $pierX - 5 }}" y="158" width="10" height="40" fill="#1e293b" />
                    @endforeach
                </svg>
                <div class="road" style="bottom: calc(100% * (230 - 148) / 230 - .2rem)">
                    @foreach ($categories->where('isExempt', false)->take(6)->values() as $index => $category)
                        <span class="car {{ $index % 2 ? 'car--rtl' : 'car--ltr' }}"
                              style="animation-name: {{ $index % 2 ? 'drive-back' : 'drive' }}; animation-duration: {{ 11 + $index * 2.5 }}s; animation-delay: -{{ $index * 3.1 }}s">{{ $category['icon'] }}</span>
                    @endforeach
                </div>
                <div class="water"></div>
            </div>
        </section>

        <section class="section" id="rates">
            <div class="wrap">
                <div class="section__head">
                    <div>
                        <div class="eyebrow">Toll Chart</div>
                        <h2 class="section__title">{{ __('Toll rates by vehicle') }}</h2>
                        <p class="section__note">{{ __('Rate per crossing (loaded / empty), in Taka.') }}</p>
                    </div>
                    <label class="search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                        <input type="search" id="rate-search" placeholder="{{ __('Search vehicle…') }}" aria-label="{{ __('Search vehicle…') }}">
                    </label>
                </div>

                @if ($categories->isEmpty())
                    <div class="empty">{{ __('Toll rates have not been published yet.') }}</div>
                @else
                    <div class="grid" id="rate-grid">
                        @foreach ($categories as $category)
                            <article @class(['card', 'card--exempt' => $category['isExempt']])
                                     style="--c: {{ $category['color'] }}; --w: {{ $category['isExempt'] ? 0 : max(4, $category['share']) }}%"
                                     data-search="{{ mb_strtolower($category['name'].' '.$category['secondaryName']) }}">
                                <div class="card__top">
                                    <span class="card__class">{{ $category['classLetter'] ?? '✓' }}</span>
                                    <span class="card__icon" aria-hidden="true">{{ $category['icon'] }}</span>
                                </div>
                                <h3 class="card__name">{{ $category['name'] }}</h3>
                                @if ($category['secondaryName'] && $category['secondaryName'] !== $category['name'])
                                    <div class="card__alt">{{ $category['secondaryName'] }}</div>
                                @endif
                                <span class="card__tag">{{ $category['isExempt'] ? __('Toll exempt') : __('Loaded / Empty') }}</span>
                                <div class="card__price">
                                    @if ($category['isExempt'])
                                        <span class="card__amount">{{ __('Free') }}</span>
                                    @else
                                        <span class="card__currency">{{ $currency }}</span>
                                        <span class="card__amount">{{ $category['formattedAmount'] }}</span>
                                    @endif
                                </div>
                                <div class="bar"><span></span></div>
                            </article>
                        @endforeach
                    </div>
                    <div class="empty" id="rate-empty" hidden>{{ __('No vehicle matches your search.') }}</div>
                @endif
            </div>
        </section>

        <section class="section" style="padding-top: 0">
            <div class="wrap info">
                <div class="info__item">
                    <span class="info__icon">⚡</span>
                    <div>
                        <div class="info__title">{{ __('Fast digital collection') }}</div>
                        <div class="info__text">{{ __('Every crossing gets a printed, numbered receipt within seconds.') }}</div>
                    </div>
                </div>
                <div class="info__item">
                    <span class="info__icon">🔒</span>
                    <div>
                        <div class="info__title">{{ __('Transparent & audited') }}</div>
                        <div class="info__text">{{ __('All transactions are recorded and synced to the central server.') }}</div>
                    </div>
                </div>
                <div class="info__item">
                    <span class="info__icon">🧾</span>
                    <div>
                        <div class="info__title">{{ __('Always ask for a receipt') }}</div>
                        <div class="info__text">
                            {{ __('Pay only the rate shown above and keep your receipt.') }}
                            @if (filled($helpline))
                                {{ __('Helpline') }}: <strong>{{ $helpline }}</strong>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="wrap footer__inner">
            <div>
                © {{ now()->year }} {{ $bridgeName }} · <span class="clock" id="clock">{{ now()->format('h:i:s A') }}</span>
            </div>
            <span class="powered">
                <span class="powered__bolt" aria-hidden="true">⚡</span>
                Powered by <strong>NextFusion</strong>
            </span>
        </div>
    </footer>

    <script>
        (() => {
            const cards = document.querySelectorAll('.card');
            const reveal = (card) => card.classList.add('is-visible');

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => reveal(entry.target), Number(entry.target.dataset.delay || 0));
                        observer.unobserve(entry.target);
                    }
                }), { threshold: 0.15 });
                cards.forEach((card, index) => { card.dataset.delay = (index % 4) * 90; observer.observe(card); });
            } else {
                cards.forEach(reveal);
            }

            const search = document.getElementById('rate-search');
            const empty = document.getElementById('rate-empty');
            search?.addEventListener('input', () => {
                const term = search.value.trim().toLowerCase();
                let shown = 0;
                cards.forEach((card) => {
                    const match = !term || card.dataset.search.includes(term);
                    card.hidden = !match;
                    if (match) { reveal(card); shown++; }
                });
                if (empty) { empty.hidden = shown > 0; }
            });

            const clock = document.getElementById('clock');
            const locale = @json($isBangla ? 'bn-BD' : 'en-US');
            const tick = () => { clock.textContent = new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
