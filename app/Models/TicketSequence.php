<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['booth_id', 'sequence_date', 'last_number'])]
class TicketSequence extends Model
{
    public $timestamps = false;
}
