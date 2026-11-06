@props(['color' => 'teal', 'label', 'value', 'hint' => null])

<div {{ $attributes->class(['tl-stat', "tl-stat--{$color}"]) }}>
    <span class="tl-stat__label">{{ $label }}</span>
    <span class="tl-stat__value">{{ $value }}</span>
    @if ($hint)
        <span class="tl-stat__hint">{{ $hint }}</span>
    @endif
</div>
