<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->qrPayload }}</title>
    <style>
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body {
            width: 58mm; margin: 0; padding: 3mm 3mm 6mm;
            font-family: 'Courier New', ui-monospace, monospace; font-size: 11px; color: #000;
        }
        .center { text-align: center; }
        .title { font-size: 13px; font-weight: 700; }
        .bn { font-family: 'Hind Siliguri', 'Nirmala UI', 'Vrinda', sans-serif; font-size: 12px; }
        .dup { display: inline-block; margin: 2px 0; padding: 1px 6px; background: #000; color: #fff; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row span:last-child { text-align: right; font-weight: 700; word-break: break-all; }
        .amount { font-size: 18px; font-weight: 800; margin: 4px 0; }
        .ticket { font-size: 12px; font-weight: 700; letter-spacing: 1px; border: 1px solid #000; padding: 2px; margin: 4px 0; }
        .small { font-size: 10px; }
    </style>
</head>
<body onload="window.focus(); window.print();">
    <div class="center">
        <div class="title">{{ $receipt->title }}</div>
        <div class="bn">{{ $receipt->subtitle }}</div>
        <div>{{ $receipt->header }}</div>
        @if ($receipt->duplicate)
            <div class="dup">DUPLICATE</div>
        @endif
    </div>
    <hr>
    @foreach ($receipt->rows as [$label, $value])
        <div class="row"><span>{{ $label }}:</span><span>{{ $value }}</span></div>
    @endforeach
    <hr>
    <div class="center amount">{{ $receipt->exempt ? 'EXEMPT ' : '' }}{{ $receipt->amount }}</div>
    <div class="center ticket">{{ $receipt->qrPayload }}</div>
    <div class="center small">{{ $receipt->footer }}</div>
    @if ($receipt->helpline)
        <div class="center small">Helpline: {{ $receipt->helpline }}</div>
    @endif
</body>
</html>
