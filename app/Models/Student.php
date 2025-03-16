<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'profile_image',
        'registration_date',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'registrations')
                    ->withPivot('payment_status', 'certificate_status')
                    ->withTimestamps();
    }

    public function certificates()
    {
        return $this->hasManyThrough(Certificate::class, Registration::class);
    }

    public function attendance()
    {
        return $this->hasManyThrough(AttendanceRecord::class, Registration::class);
    }
}