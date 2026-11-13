<?php

namespace App\Providers;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Booth;
use App\Models\MobileDevice;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\TollRate;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureModels();
        $this->configureFilament();
        $this->registerAuthAudit();
    }

    private function configureModels(): void
    {
        // Throw in development when a relationship is lazy loaded inside a loop (N+1 guard).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::morphMap([
            'booth' => Booth::class,
            'user' => User::class,
            'vehicle_category' => VehicleCategory::class,
            'toll_rate' => TollRate::class,
            'shift' => Shift::class,
            'shift_session' => ShiftSession::class,
            'toll_transaction' => TollTransaction::class,
            'vehicle' => Vehicle::class,
            'setting' => Setting::class,
            'audit_log' => AuditLog::class,
            'mobile_device' => MobileDevice::class,
        ]);
    }

    private function configureFilament(): void
    {
        // Every label written in English is translated through lang/*.json.
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $filter->translateLabel());

        // Edit and delete actions render as icon-only buttons; the label stays available as a tooltip.
        EditAction::configureUsing(fn (EditAction $action) => $action
            ->iconButton()
            ->tooltip(fn (): string => $action->getLabel()));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action
            ->iconButton()
            ->tooltip(fn (): string => $action->getLabel()));

        FilamentAsset::register([
            Css::make('toll', resource_path('css/toll.css')),
            Js::make('toll', resource_path('js/toll.js')),
        ]);
    }

    private function registerAuthAudit(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                app(AuditLogger::class)->log(AuditEvent::Login, actor: $event->user);
            }
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->log(AuditEvent::Logout, actor: $event->user);
            }
        });
    }
}
