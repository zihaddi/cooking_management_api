<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'date',
        'present',
    ];

    protected $casts = [
        'date' => 'date',
        'present' => 'boolean',
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