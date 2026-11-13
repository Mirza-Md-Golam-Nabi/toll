<?php

namespace App\Filament\Pages;

use App\Enums\AppMode;
use App\Enums\NavigationGroup;
use App\Enums\Permission;
use App\Filament\Support\Feedback;
use App\Models\Booth;
use App\Models\TollTransaction;
use App\Services\Receipt\ReceiptPrinter;
use App\Services\Sync\SyncService;
use App\Support\Settings as AppSettings;
use App\Support\Toll;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Receipt / branding settings plus system status (mode, printer, sync).
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Toll::userCan(Permission::ManageSettings);
    }

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    public function mount(): void
    {
        $this->form->fill(AppSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(! Toll::canEditMasterData())
            ->components([
                Section::make(__('Receipt & branding'))
                    ->description(__('Shown on the panel header and on every printed receipt.'))
                    ->columns(2)
                    ->components([
                        TextInput::make('bridge_name_bn')->label('Bridge name (Bangla)')->required()->maxLength(120),
                        TextInput::make('bridge_name_en')->label('Bridge name (English)')->required()->maxLength(120),
                        TextInput::make('receipt_header')->label('Receipt header')->required()->maxLength(40),
                        TextInput::make('receipt_footer')->label('Receipt footer')->maxLength(64),
                        TextInput::make('helpline')->label('Helpline')->tel()->maxLength(20),
                        TextInput::make('currency')->label('Currency symbol')->required()->maxLength(5),
                    ]),
            ]);
    }

    public function save(): void
    {
        AppSettings::set($this->form->getState());

        Notification::make()->title(__('Settings saved.'))->success()->send();
    }

    /** @return array<string, string> */
    public function systemInfo(): array
    {
        return [
            __('Mode') => AppMode::isCentral() ? __('Central server') : __('Booth'),
            __('Booth code') => (string) config('toll.booth_code'),
            __('Booth') => Booth::current()?->name ?? '—',
            __('Printer') => config('toll.printer.driver') === 'escpos'
                ? 'ESC/POS · '.config('toll.printer.connector').' · '.config('toll.printer.target')
                : __('Browser printing'),
            __('Central server') => Toll::syncEnabled() ? (string) config('toll.sync.central_url') : __('Not configured'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label(__('Sync now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('info')
                ->visible(fn (): bool => AppMode::isBooth() && Toll::syncEnabled())
                ->action(function (SyncService $sync): void {
                    $result = $sync->run();
                    $result['ok']
                        ? Notification::make()->title(__('Sync completed.'))->success()->send()
                        : Feedback::error($result['message']);
                }),
            Action::make('testPrint')
                ->label(__('Test print'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => app(ReceiptPrinter::class)->isDirect())
                ->action(function (ReceiptPrinter $printer): void {
                    $latest = TollTransaction::query()->newestFirst()->first();

                    $latest && $printer->print($latest)
                        ? Notification::make()->title(__('Test receipt printed.'))->success()->send()
                        : Feedback::error(__('Printer not reachable, or no ticket to print yet.'));
                }),
        ];
    }
}
