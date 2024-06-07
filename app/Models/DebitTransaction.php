<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'control_balance_id',
        'transaction_reference',
        'amount',
        'description',
    ];
}
