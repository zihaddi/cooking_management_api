<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title_en',
        'title_bn',
        'description_en',
        'description_bn',
        'start_date',
        'end_date',
        'daily_start_time',
        'daily_end_time',
        'location_details',
        'maximum_capacity',
        'current_enrollment',
        'price',
        'status',
        'featured_image',
        'category',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_start_time' => 'datetime',
        'daily_end_time' => 'datetime',
    ];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class)
                    ->withPivot('day_number')
                    ->withTimestamps();
    }

    public function instructors()
    {
        return $this->belongsToMany(Instructor::class)
                    ->withPivot('is_lead')
                    ->withTimestamps();
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'registrations')
                    ->withPivot('payment_status', 'certificate_status')
                    ->withTimestamps();
    }

    public function getAvailableSeatsAttribute()
    {
        return $this->maximum_capacity - $this->current_enrollment;
    }
}
