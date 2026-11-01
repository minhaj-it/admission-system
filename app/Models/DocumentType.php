<?php

namespace App\Models;

use App\Models\Concerns\MasterRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentType extends Model
{
    use MasterRecord;

    protected $table = 'document_types';

    protected $fillable = ['name', 'is_active', 'sort_order', 'key', 'kind', 'step'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function usageCount(): int
    {
        return DB::table('offering_documents')->where('document_type_id', $this->id)->count();
    }
}
