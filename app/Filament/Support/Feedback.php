<?php

namespace App\Filament\Support;

use App\Exceptions\TollException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Runs a business operation and turns the outcome into a Filament notification.
 * Business-rule failures keep the action modal open so the user can correct input.
 */
final class Feedback
{
    public static function attempt(callable $operation, ?string $successMessage = null, ?Action $action = null): mixed
    {
        try {
            $result = $operation();
        } catch (TollException $e) {
            self::error($e->getMessage());
            $action?->halt();

            return null;
        }

        if ($successMessage) {
            Notification::make()->title($successMessage)->success()->send();
        }

        return $result;
    }

    public static function error(string $message): void
    {
        Notification::make()->title($message)->danger()->send();
    }
}
