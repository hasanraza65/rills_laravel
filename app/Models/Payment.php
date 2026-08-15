<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'parent_id',
        'amount_paid',
        'wallet_used',
        'extra_added_to_wallet',
        'payment_method',
        'bank_name',
        'reference_no',
        'payment_date',
    ];

    public function items()
    {
        return $this->hasMany(PaymentItem::class);
    }
}
