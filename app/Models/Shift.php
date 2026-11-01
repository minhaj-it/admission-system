<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Shift extends Model
{
    use MasterRecord;

    protected $table = 'shifts';

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('offering_shifts')->where('shift_id', $this->id)->count();
    }
}
