<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AcademicSession extends Model
{
    use MasterRecord;

    protected $table = 'academic_sessions';

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('circular_offerings')->where('academic_session_id', $this->id)->count();
    }
}
