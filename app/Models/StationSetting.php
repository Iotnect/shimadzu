<?php

namespace App\Models;

use Database\Factories\StationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sensor', 'station', 'effective_from', 'standard_minutes', 'working_minutes', 'notes', 'changed_by', 'changed_by_name'])]
class StationSetting extends Model
{
    /** @use HasFactory<StationSettingFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_date',
            'standard_minutes' => 'decimal:4',
            'working_minutes' => 'decimal:4',
        ];
    }
}
