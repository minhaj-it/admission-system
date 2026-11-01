<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $table = 'guardians';

    protected $fillable = ['role','name','phone','education','nid','occupation','designation','office_address','monthly_income','guardian_type','relation'];

    protected function casts(): array
    {
        return ['nid' => 'encrypted', 'monthly_income' => 'decimal:2'];
    }
}
