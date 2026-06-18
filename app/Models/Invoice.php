<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function parent()
    {
        return $this->belongsTo(ParentProfile::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}