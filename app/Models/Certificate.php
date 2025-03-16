<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'registration_id',
        'certificate_number',
        'issue_date',
        'digital_signature',
        'pdf_path',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
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