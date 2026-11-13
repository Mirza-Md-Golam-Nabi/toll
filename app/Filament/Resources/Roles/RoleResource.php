<?php

namespace App\Filament\Resources\Roles;

use App\Enums\NavigationGroup;
use App\Enums\Permission;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use UnitEnum;

use function Filament\Support\generate_icon_html;

/**
 * The Super Admin chooses which permissions the Plaza Manager and Booth Operator roles hold.
 */
class RoleResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
            ->components(collect(Permission::grouped())
                ->map(fn (array $permissions, string $group): Fieldset => Fieldset::make(Permission::groupLabel($group))
                    ->columns(1)
                    ->schema([
                        CheckboxList::make("permissions.{$group}")
                            ->hiddenLabel()
                            ->allowHtml()
                            ->options(collect($permissions)->mapWithKeys(fn (Permission $permission): array => [$permission->value => self::permissionOptionLabel($permission)])->all()),
                    ]))
                ->values()
                ->all());
    }

    /**
     * The permission's label followed by a circled "i" icon whose tooltip explains the permission.
     * Clicking the icon only opens the tooltip; it does not toggle the checkbox.
     */
    protected static function permissionOptionLabel(Permission $permission): HtmlString
    {
        $description = $permission->description();

        $icon = generate_icon_html(Heroicon::OutlinedInformationCircle, size: IconSize::Small)?->toHtml();

        return new HtmlString(
            e($permission->getLabel())
            .'<span tabindex="0" role="img" aria-label="'.e($description).'"'
            .' class="fi-role-permission-info"'
            .' style="display:inline-flex;vertical-align:middle;margin-inline-start:0.375rem;color:var(--gray-400);cursor:help;"'
            .' x-on:click.prevent.stop'
            .' x-tooltip="{ content: '.e(Js::from($description)->toHtml()).', theme: $store.theme, maxWidth: 320 }">'
            .$icon
            .'</span>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['permissions', 'members']))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Role')->weight('semibold')
                    ->badge()
                    ->formatStateUsing(fn (Role $record): string => $record->userRole()->getLabel())
                    ->color(fn (Role $record): string => $record->userRole()->getColor()),
                TextColumn::make('permissions_count')->label('Permissions')->alignCenter()
                    ->formatStateUsing(fn (Role $record, int $state): string => $record->isSuperAdmin()
                        ? __('All')
                        : $state.' / '.count(Permission::cases())),
                TextColumn::make('members_count')->label('Users')->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()->label('Permissions')->icon(Heroicon::OutlinedKey),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
