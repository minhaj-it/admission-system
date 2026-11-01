<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StudyGroup extends Model
{
    use MasterRecord;

    protected $table = 'study_groups';

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('offering_groups')->where('study_group_id', $this->id)->count();
    }
}
