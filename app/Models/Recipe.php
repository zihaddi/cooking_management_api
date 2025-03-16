<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_bn',
        'description_en',
        'description_bn',
        'ingredients',
        'instructions',
        'preparation_time',
        'difficulty_level',
    ];

    protected $casts = [
        'ingredients' => 'array',
        'instructions' => 'array',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class)
                    ->withPivot('day_number')
                    ->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(RecipeImage::class);
    }

    public function primaryImage()
    {
        return $this->images()->where('is_primary', true)->first();
    }
}