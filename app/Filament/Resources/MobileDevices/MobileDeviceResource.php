<?php

namespace App\Filament\Resources\MobileDevices;

use App\Enums\AppMode;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\MobileDevices\Pages\ManageMobileDevices;
use App\Filament\Support\Columns;
use App\Models\MobileDevice;
use App\Services\Mobile\MobileDeviceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Android phones running the offline collection app. Phones register themselves on first
 * sign in; managers can rename or block them here (a blocked phone stops syncing at once).
 */
class MobileDeviceResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = MobileDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 35;

    public static function shouldRegisterNavigation(): bool
    {
        return AppMode::isCentral() && parent::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Name')->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['booth:id,code,name', 'user:id,name']))
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('booth.code')->label('Booth')->badge()->color('primary')->sortable(),
                TextColumn::make('code')->label('Code')->badge()->color('gray')->searchable(),
                TextColumn::make('name')->label('Name')->searchable()->placeholder('—'),
                TextColumn::make('user.name')->label('Signed in operator')->placeholder(__('Signed out')),
                Columns::active(),
                Columns::dateTime('last_seen_at', 'Last seen')->placeholder(__('Never')),
                Columns::dateTime('last_synced_at', 'Last synced')->placeholder(__('Never')),
            ])
            ->filters([
                SelectFilter::make('booth_id')->label('Booth')->relationship('booth', 'name')->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('block')
                    ->label(__('Block'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The phone is signed out at once and cannot sync until it is unblocked. Tickets not yet synced stay on the phone.'))
                    ->visible(fn (MobileDevice $record): bool => $record->is_active)
                    ->authorize('update')
                    ->action(fn (MobileDevice $record) => app(MobileDeviceService::class)->block($record)),
                Action::make('unblock')
                    ->label(__('Unblock'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MobileDevice $record): bool => ! $record->is_active)
                    ->authorize('update')
                    ->action(fn (MobileDevice $record) => $record->update(['is_active' => true])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMobileDevices::route('/'),
        ];
    }
}
