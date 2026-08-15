<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentItem extends Model
{
    protected $fillable = [
        'payment_id',
        'invoice_item_id',
        'paid_amount',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}