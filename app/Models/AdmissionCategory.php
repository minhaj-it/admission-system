<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionCategory extends Model
{
    use MasterRecord;

    protected $table = 'admission_categories';

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('offering_categories')->where('admission_category_id', $this->id)->count();
    }
}
