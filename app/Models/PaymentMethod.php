<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    // is_active is changed only by PaymentMethodService, which keeps at most one method active.
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
