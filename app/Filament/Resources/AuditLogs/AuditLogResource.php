<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditEvent;
use App\Enums\NavigationGroup;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Support\Columns;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class AuditLogResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 20;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')->label('Time')->dateTime('d M Y, h:i:s A'),
                TextEntry::make('event')->label('Event')->badge(),
                TextEntry::make('user.name')->label('User')->placeholder('System'),
                TextEntry::make('approver.name')->label('Approved by')->placeholder('—'),
                TextEntry::make('subject')->label('Subject')->formatStateUsing(fn (?string $state) => $state ? __(Str::headline($state)) : null)->placeholder('—'),
                TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                TextEntry::make('reason')->label('Reason')->placeholder('—')->columnSpanFull(),
                KeyValueEntry::make('old_values')->label('Before')->placeholder('—'),
                KeyValueEntry::make('new_values')->label('After')->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,name', 'approver:id,name', 'booth:id,code']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Columns::dateTime('created_at', 'Time'),
                Columns::badge('event', 'Event'),
                TextColumn::make('user.name')->label('User')->placeholder('System')->searchable(),
                TextColumn::make('subject')->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? __(Str::headline($state)) : null)
                    ->placeholder('—'),
                TextColumn::make('reason')->label('Reason')->limit(40)->placeholder('—')->wrap(),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('—')->toggleable(),
                TextColumn::make('booth.code')->label('Booth')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->label('Event')->options(AuditEvent::class)->multiple(),
                SelectFilter::make('user_id')->label('User')->relationship('user', 'name')->searchable()->preload(),
                TernaryFilter::make('overrides')
                    ->label('Manual overrides only')
                    ->queries(
                        true: fn (Builder $query) => $query->overrides(),
                        false: fn (Builder $query) => $query->whereNotIn('event', AuditEvent::overrides()),
                    ),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<', now()->parse($date)->addDay()))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
