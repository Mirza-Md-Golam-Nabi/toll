{{-- Aggregate table: one row per group with the shared vehicles / paid / exempt / voided / revenue columns. --}}
@props(['title', 'rows', 'labelHeading', 'bars' => false])

@php
    $maxRevenue = max(1, (float) $rows->max('revenue'));
@endphp

<section class="tl-panel">
    <header class="tl-panel__header">
        <h2>{{ $title }}</h2>
        <span class="tl-muted">{{ trans_choice(':count row|:count rows', $rows->count(), ['count' => $rows->count()]) }}</span>
    </header>
    <div class="tl-table-wrap">
        <table class="tl-table">
            <thead>
                <tr>
                    <th>{{ $labelHeading }}</th>
                    <th class="tl-num">{{ __('Vehicles') }}</th>
                    <th class="tl-num">{{ __('Exempt') }}</th>
                    <th class="tl-num">{{ __('Voided') }}</th>
                    <th class="tl-num">{{ __('Revenue') }}</th>
                    @if ($bars)
                        <th>{{ __('Share') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @isset($row['color'])
                                <span class="tl-swatch" style="--tile: {{ $row['color'] }}"></span>
                            @endisset
                            {{ $row['label'] }}
                        </td>
                        <td class="tl-num">{{ number_format($row['vehicles']) }}</td>
                        <td class="tl-num">{{ number_format($row['exempt_count']) }}</td>
                        <td class="tl-num">{{ number_format($row['voided_count']) }}</td>
                        <td class="tl-num"><strong>{{ \App\Support\Money::format($row['revenue']) }}</strong></td>
                        @if ($bars)
                            <td>
                                <div class="tl-bar"><span style="width: {{ round($row['revenue'] / $maxRevenue * 100, 1) }}%; --tile: {{ $row['color'] ?? '#14b8a6' }}"></span></div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bars ? 6 : 5 }}" class="tl-empty">{{ __('No data for the selected filters.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td>{{ __('Total') }}</td>
                        <td class="tl-num">{{ number_format($rows->sum('vehicles')) }}</td>
                        <td class="tl-num">{{ number_format($rows->sum('exempt_count')) }}</td>
                        <td class="tl-num">{{ number_format($rows->sum('voided_count')) }}</td>
                        <td class="tl-num">{{ \App\Support\Money::format($rows->sum('revenue')) }}</td>
                        @if ($bars)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>
