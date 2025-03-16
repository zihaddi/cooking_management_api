<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title_en');
            $table->string('title_bn')->nullable();
            $table->text('description_en');
            $table->text('description_bn')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('daily_start_time');
            $table->time('daily_end_time');
            $table->text('location_details');
            $table->integer('maximum_capacity');
            $table->integer('current_enrollment')->default(0);
            $table->decimal('price', 10, 2);
            $table->enum('status', ['upcoming', 'active', 'completed', 'canceled'])->default('upcoming');
            $table->string('featured_image')->nullable();
            $table->string('category');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
