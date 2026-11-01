<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $table = 'student_profiles';

    protected $fillable = ['name','name_bn','dob','birth_reg_no','emergency_phone','alt_phone','blood_group','nationality','religion','gender'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'birth_reg_no' => 'encrypted'];
    }
}
