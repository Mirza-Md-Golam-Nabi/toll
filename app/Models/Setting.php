<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use Auditable;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::saved(fn () => Settings::flush());
    }
}
