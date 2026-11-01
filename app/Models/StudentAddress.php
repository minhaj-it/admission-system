<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAddress extends Model
{
    protected $table = 'student_addresses';

    protected $fillable = ['kind','address'];
}
