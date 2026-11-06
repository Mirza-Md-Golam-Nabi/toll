<x-filament-panels::page>
    @php
        $totals = $this->totals;
        $filters = $this->reportFilters;
    @endphp

    <div class="tl-report">
        <section class="tl-panel tl-no-print">
            {{ $this->filtersForm }}
        </section>

        <p class="tl-muted">
            {{ __('Period') }}: <strong>{{ $filters->from->format('d M Y') }}</strong> — <strong>{{ $filters->until->format('d M Y') }}</strong>
        </p>

        <section class="tl-stats" wire:loading.class="opacity-60">
            <x-stat-card color="teal" :label="__('Total revenue')" :value="\App\Support\Money::format($totals['revenue'])"
                :hint="__(':count paid tickets', ['count' => number_format($totals['paid_count'])])" />
            <x-stat-card color="indigo" :label="__('Vehicles')" :value="number_format($totals['vehicles'])"
                :hint="__('Average per day: :avg', ['avg' => number_format($totals['vehicles'] / max(1, $this->daily->count()), 1)])" />
            <x-stat-card color="sky" :label="__('Exempt vehicles')" :value="number_format($totals['exempt_count'])" />
            <x-stat-card color="rose" :label="__('Voided tickets')" :value="number_format($totals['voided_count'])" />
        </section>

        <div class="tl-grid-2">
            <x-report-table :title="__('Category-wise collection')" :label-heading="__('Vehicle category')" :rows="$this->categories" bars />
            <x-report-table :title="__('Daily collection')" :label-heading="__('Date')" :rows="$this->daily" />
        </div>

        <section class="tl-panel">
            <header class="tl-panel__header">
                <h2>{{ __('Shift-wise collection') }}</h2>
            </header>
            <div class="tl-table-wrap">
                <table class="tl-table">
                    <thead>
                        <tr>
                            <th>{{ __('Shift') }}</th>
                            <th>{{ __('Operator') }}</th>
                            <th>{{ __('Opened at') }}</th>
                            <th class="tl-num">{{ __('Vehicles') }}</th>
                            <th class="tl-num">{{ __('Revenue') }}</th>
                            <th class="tl-num">{{ __('Expected cash') }}</th>
                            <th class="tl-num">{{ __('Declared cash') }}</th>
                            <th class="tl-num">{{ __('Difference') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->shifts as $shift)
                            @php
                                $diff = $shift['cash_difference'];
                                $diffClass = $diff === null ? '' : ((float) $diff < 0 ? 'tl-neg' : ((float) $diff > 0 ? 'tl-pos' : 'tl-zero'));
                            @endphp
                            <tr>
                                <td>{{ $shift['label'] }} <span class="tl-muted">({{ $shift['booth'] }})</span></td>
                                <td>{{ $shift['operator'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($shift['opened_at'])->format('d M, h:i A') }}</td>
                                <td class="tl-num">{{ number_format($shift['vehicles']) }}</td>
                                <td class="tl-num"><strong>{{ \App\Support\Money::format($shift['revenue']) }}</strong></td>
                                <td class="tl-num">{{ $shift['expected_cash'] === null ? __('Open') : \App\Support\Money::format($shift['expected_cash']) }}</td>
                                <td class="tl-num">{{ $shift['declared_cash'] === null ? '—' : \App\Support\Money::format($shift['declared_cash']) }}</td>
                                <td class="tl-num {{ $diffClass }}">{{ $diff === null ? '—' : \App\Support\Money::format($diff) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="tl-empty">{{ __('No data for the selected filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <x-report-table :title="__('Operator-wise collection')" :label-heading="__('Operator')" :rows="$this->operators" />
    </div>
</x-filament-panels::page>
