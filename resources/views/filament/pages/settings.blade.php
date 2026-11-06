<x-filament-panels::page>
    <div class="tl-grid-2">
        <form wire:submit="save" class="tl-panel">
            {{ $this->form }}

            @if (\App\Support\Toll::canEditMasterData())
                <div class="tl-mt">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        {{ __('Save') }}
                    </x-filament::button>
                </div>
            @else
                <p class="tl-muted tl-mt">{{ __('Settings are managed on the central server and synced to this booth.') }}</p>
            @endif
        </form>

        <section class="tl-panel">
            <header class="tl-panel__header">
                <h2>{{ __('System status') }}</h2>
                <x-connection-status />
            </header>
            <table class="tl-table">
                <tbody>
                    @foreach ($this->systemInfo() as $label => $value)
                        <tr>
                            <td class="tl-muted">{{ $label }}</td>
                            <td><strong>{{ $value }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="tl-muted tl-mt">{{ __('Mode, booth code, printer and central server are configured in the .env file on each installation.') }}</p>
        </section>
    </div>
</x-filament-panels::page>
