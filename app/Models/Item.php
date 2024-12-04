<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $guarded = [];

    public function invoice()
    {
        return $this->belongsToMany(Invoice::class, 'items_invoices')
            ->withPivot('quantity')
            ->withPivot('amount');
    }
}
