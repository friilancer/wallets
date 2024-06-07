<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogs extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'credit_transaction_id',
        'debit_transaction_id',
        'action',
        'description',
        'status',
        'anomaly',
    ];
}
