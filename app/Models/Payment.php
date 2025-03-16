<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'registration_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_date',
        'payment_proof',
        'verification_status',
        'rejection_reason',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function student()
    {
        return $this->registration->student();
    }

    public function course()
    {
        return $this->registration->course();
    }
}