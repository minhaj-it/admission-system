<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SchoolClass extends Model
{
    use MasterRecord;

    protected $table = 'school_classes';

    protected $fillable = ['name', 'is_active', 'sort_order', 'requires_group'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'requires_group' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('circular_offerings')->where('school_class_id', $this->id)->count();
    }
}
