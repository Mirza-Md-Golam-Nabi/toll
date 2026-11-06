<?php

namespace App\Filament\Resources\Users;

use App\Enums\NavigationGroup;
use App\Enums\UserRole;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Name')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Phone')->tel()->maxLength(20),
                Select::make('role')->label('Role')->options(UserRole::class)->required()->live()->native(false),
                Select::make('booth_id')->label('Booth')->relationship('booth', 'name')->preload()->native(false),
                Select::make('locale')->label('Language')->options(['bn' => 'বাংলা', 'en' => 'English'])->default('bn')->required()->native(false),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(6),
                TextInput::make('approval_pin')
                    ->label('Approval PIN')
                    ->password()
                    ->revealable()
                    ->numeric()
                    ->minLength(4)
                    ->maxLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->visible(fn (Get $get): bool => self::roleOf($get('role'))?->canApproveOverrides() ?? false)
                    ->helperText(__('Used to approve operator overrides. Leave blank to keep the current PIN.')),
                Fields::active(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('booth:id,code'))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->weight('semibold')
                    ->description(fn (User $record): ?string => $record->phone),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Columns::badge('role', 'Role'),
                TextColumn::make('booth.code')->label('Booth')->badge()->color('gray')->placeholder('—'),
                Columns::active(),
                Columns::dateTime('last_login_at', 'Last login')->placeholder(__('Never')),
            ])
            ->filters([
                SelectFilter::make('role')->label('Role')->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

    private static function roleOf(mixed $role): ?UserRole
    {
        return $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
    }
}
