<x-filament-panels::page>
    @php
        $session = $this->session;
    @endphp

    <div
        class="tl-collection"
        x-data="{
            busy: false,
            shortcuts: @js($this->shortcuts()),
            issue(id) {
                if (this.busy) return;
                this.busy = true;
                $wire.issue(id).finally(() => { this.busy = false; this.$refs.plate?.focus(); });
            },
            handleKey(event) {
                const id = this.shortcuts[event.key];
                if (! id || ! @js((bool) $session)) return;
                event.preventDefault();
                this.issue(id);
            },
        }"
        x-on:keydown.window="handleKey($event)"
        x-on:toll-ticket-issued.window="$refs.plate?.focus()"
    >
        {{-- Shift status strip --}}
        @if ($this->canViewShiftSummary)
            @php($summary = $this->summary)
            <section class="tl-stats">
                <x-stat-card color="teal" :label="__('Shift')" :value="$session?->shift->name ?? __('No open shift')"
                    :hint="$session ? __('Opened at').' '.$session->opened_at->format('h:i A') : __('Open a shift to start collecting')" />
                <x-stat-card color="indigo" :label="__('Vehicles')" :value="number_format($summary['vehicles'])"
                    :hint="__('Exempt').': '.$summary['exempt_count'].' · '.__('Voided').': '.$summary['voided_count']" />
                <x-stat-card color="amber" :label="__('Collected this shift')" :value="\App\Support\Money::format($summary['revenue'])"
                    :hint="__('Opening cash').': '.\App\Support\Money::format($session?->opening_cash)" />
                <x-stat-card color="rose" :label="__('Booth')" :value="$session?->booth->code ?? config('toll.booth_code')"
                    :hint="auth()->user()->name" />
            </section>
        @endif

        <div class="tl-layout">
            {{-- Category tiles --}}
            <section class="tl-panel">
                <header class="tl-panel__header">
                    <h2>{{ __('Select vehicle category') }}</h2>
                    <span class="tl-muted">{{ __('Click a tile or press its shortcut key') }}</span>
                </header>

                <div @class(['tl-tiles', 'tl-tiles--locked' => ! $session])>
                    @foreach ($this->categories as $category)
                        <button
                            type="button"
                            wire:key="cat-{{ $category->id }}"
                            class="tl-tile"
                            style="--tile: {{ $category->color }}"
                            x-on:click="issue({{ $category->id }})"
                            x-bind:disabled="busy"
                            @disabled(! $session)
                        >
                            @if ($category->shortcut_key)
                                <kbd class="tl-tile__key">{{ $category->shortcut_key }}</kbd>
                            @endif
                            <span class="tl-tile__icon">{{ $category->icon ?: '🚘' }}</span>
                            <span class="tl-tile__name">{{ $category->name_bn }}</span>
                            @if ($category->name_en)
                                <span class="tl-tile__sub">{{ $category->name_en }}</span>
                            @endif
                            <span class="tl-tile__amount">
                                {{ $category->is_exempt ? __('Free') : \App\Support\Money::format($category->current_amount) }}
                            </span>
                        </button>
                    @endforeach

                    @unless ($session)
                        <div class="tl-locked">
                            <x-filament::icon icon="heroicon-o-lock-closed" class="tl-locked__icon" />
                            <p>{{ __('Open a shift to start collecting') }}</p>
                        </div>
                    @endunless
                </div>
            </section>

            {{-- Plate + recent tickets --}}
            <aside class="tl-side">
                <section class="tl-panel">
                    <header class="tl-panel__header">
                        <h2>{{ __('Number plate') }}</h2>
                        <span class="tl-badge">{{ __('Optional') }}</span>
                    </header>
                    <input
                        x-ref="plate"
                        type="text"
                        wire:model.live.debounce.300ms="plateNumber"
                        class="tl-plate"
                        placeholder="DHAKA METRO-GA 12-3456"
                        autocomplete="off"
                        autofocus
                    />
                    @if ($this->plateSuggestions->isNotEmpty())
                        <div class="tl-suggestions">
                            @foreach ($this->plateSuggestions as $plate)
                                <button type="button" wire:key="plate-{{ $plate }}" wire:click="choosePlate(@js($plate))" class="tl-chip">{{ $plate }}</button>
                            @endforeach
                        </div>
                    @endif
                    <p class="tl-muted tl-mt">{{ __('Type the plate (if needed), then press the category key.') }}</p>
                </section>

                <section class="tl-panel">
                    <header class="tl-panel__header">
                        <h2>{{ __('Recent tickets') }}</h2>
                    </header>
                    <ul class="tl-recent">
                        @forelse ($this->recentTickets as $ticket)
                            <li wire:key="ticket-{{ $ticket->id }}" @class(['tl-recent__item', 'tl-recent__item--void' => $ticket->isVoided()])>
                                <span class="tl-recent__dot" style="--tile: {{ $ticket->category->color }}">{{ $ticket->category->icon ?: '🚘' }}</span>
                                <div class="tl-recent__body">
                                    <strong>{{ $ticket->ticket_no }}</strong>
                                    <span class="tl-muted">{{ $ticket->category->display_name }} · {{ $ticket->issued_at->format('h:i:s A') }}{{ $ticket->plate_number ? ' · '.$ticket->plate_number : '' }}</span>
                                </div>
                                <span class="tl-recent__amount">{{ \App\Support\Money::format($ticket->amount) }}</span>
                                @unless ($ticket->isVoided())
                                    <x-filament::icon-button
                                        icon="heroicon-o-printer"
                                        color="gray"
                                        :label="__('Reprint')"
                                        wire:click="reprint('{{ $ticket->id }}')"
                                        wire:confirm="{{ __('Reprint this receipt as DUPLICATE?') }}"
                                    />
                                @endunless
                            </li>
                        @empty
                            <li class="tl-empty">{{ __('No tickets yet in this shift.') }}</li>
                        @endforelse
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
