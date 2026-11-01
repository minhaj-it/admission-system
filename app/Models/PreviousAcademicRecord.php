<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreviousAcademicRecord extends Model
{
    protected $table = 'previous_academic_records';

    protected $fillable = ['institute','class_name','result','roll','version_text'];
}
