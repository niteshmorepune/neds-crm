<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSequence extends Model
{
    protected $fillable = [
        'financial_year',
        'last_number',
    ];
}
