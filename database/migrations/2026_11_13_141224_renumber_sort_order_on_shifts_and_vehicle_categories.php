<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sort order is now a gap-free 1..n sequence (see HasSortOrder). Renumbers existing rows,
 * keeping their current order, in one CASE update per table.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['shifts', 'vehicle_categories'] as $table) {
            $ids = DB::table($table)->orderBy('sort_order')->orderBy('id')->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $cases = $ids->map(fn (int $id, int $index): string => 'when '.$id.' then '.($index + 1))->implode(' ');

            DB::table($table)->whereIn('id', $ids)->update(['sort_order' => DB::raw('case id '.$cases.' end')]);
        }
    }

    public function down(): void
    {
        //
    }
};
