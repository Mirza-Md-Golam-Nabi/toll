<?php

namespace App\Filament\Resources\ShiftSessions;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\ScopesRecordsToOperator;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\ShiftSessions\Pages\ListShiftSessions;
use App\Filament\Resources\ShiftSessions\Pages\ViewShiftSession;
use App\Filament\Resources\ShiftSessions\Schemas\ShiftSessionInfolist;
use App\Filament\Resources\ShiftSessions\Tables\ShiftSessionsTable;
use App\Models\ShiftSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShiftSessionResource extends Resource
{
    use ScopesRecordsToOperator, TranslatesModelLabels;

    protected static ?string $model = ShiftSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return ShiftSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftSessions::route('/'),
            'view' => ViewShiftSession::route('/{record}'),
        ];
    }
}
