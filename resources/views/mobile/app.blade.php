<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="/mobile/manifest.webmanifest">
    <link rel="icon" href="/mobile/icons/icon-192.png">
    <link rel="stylesheet" href="/mobile/app.css?v={{ $version }}">
</head>
<body>
    <noscript>JavaScript is required.</noscript>
    <div id="app" class="app" aria-live="polite"></div>
    <div id="toast" class="toast" role="status" hidden></div>
    <div id="modal-root"></div>
    <div id="print-area" class="print-area" aria-hidden="true"></div>
    <script src="/mobile/sync-core.js?v={{ $version }}" defer></script>
    <script src="/mobile/app.js?v={{ $version }}" defer></script>
</body>
</html>
