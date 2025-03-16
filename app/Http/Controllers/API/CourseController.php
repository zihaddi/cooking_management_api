<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show', 'checkAvailability']);
    }

    public function index(Request $request)
    {
        $query = Course::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Sort by date or price
        if ($request->has('sort_by')) {
            if ($request->sort_by === 'price_asc') {
                $query->orderBy('price', 'asc');
            } elseif ($request->sort_by === 'price_desc') {
                $query->orderBy('price', 'desc');
            } elseif ($request->sort_by === 'date_asc') {
                $query->orderBy('start_date', 'asc');
            } elseif ($request->sort_by === 'date_desc') {
                $query->orderBy('start_date', 'desc');
            }
        } else {
            // Default sorting by start date descending
            $query->orderBy('start_date', 'desc');
        }

        $courses = $query->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully',
            'data' => $courses
        ], 200);
    }


    public function store(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('create courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title_en' => 'required|string|max:255',
            'title_bn' => 'nullable|string|max:255',
            'description_en' => 'required|string',
            'description_bn' => 'nullable|string',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'daily_start_time' => 'required|date_format:H:i',
            'daily_end_time' => 'required|date_format:H:i|after:daily_start_time',
            'location_details' => 'required|string',
            'maximum_capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle featured image upload
        $featuredImagePath = null;
        if ($request->hasFile('featured_image')) {
            $image = $request->file('featured_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $featuredImagePath = $image->storeAs('courses/images', $filename, 'public');
        }

        $course = Course::create([
            'title_en' => $request->title_en,
            'title_bn' => $request->title_bn,
            'description_en' => $request->description_en,
            'description_bn' => $request->description_bn,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'daily_start_time' => $request->daily_start_time,
            'daily_end_time' => $request->daily_end_time,
            'location_details' => $request->location_details,
            'maximum_capacity' => $request->maximum_capacity,
            'current_enrollment' => 0,
            'price' => $request->price,
            'status' => 'upcoming',
            'featured_image' => $featuredImagePath,
            'category' => $request->category,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => $course
        ], 201);
    }

    public function show($id)
    {
        $course = Course::with(['instructors', 'recipes'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Course retrieved successfully',
            'data' => $course
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('edit courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title_en' => 'sometimes|required|string|max:255',
            'title_bn' => 'nullable|string|max:255',
            'description_en' => 'sometimes|required|string',
            'description_bn' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'daily_start_time' => 'sometimes|required|date_format:H:i',
            'daily_end_time' => 'sometimes|required|date_format:H:i|after:daily_start_time',
            'location_details' => 'sometimes|required|string',
            'maximum_capacity' => 'sometimes|required|integer|min:' . $course->current_enrollment,
            'price' => 'sometimes|required|numeric|min:0',
            'category' => 'sometimes|required|string|max:100',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($course->featured_image) {
                Storage::disk('public')->delete($course->featured_image);
            }

            $image = $request->file('featured_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $featuredImagePath = $image->storeAs('courses/images', $filename, 'public');
            
            $course->featured_image = $featuredImagePath;
        }

        // Update course fields
        $course->fill($request->only([
            'title_en', 'title_bn', 'description_en', 'description_bn',
            'start_date', 'end_date', 'daily_start_time', 'daily_end_time',
            'location_details', 'maximum_capacity', 'price', 'category'
        ]));

        $course->save();

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'data' => $course
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('delete courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $course = Course::findOrFail($id);

        // Check if there are any students enrolled
        if ($course->current_enrollment > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete course with enrolled students',
            ], 400);
        }

        // Delete featured image if exists
        if ($course->featured_image) {
            Storage::disk('public')->delete($course->featured_image);
        }

        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully',
        ], 200);
    }

    public function getRecipes($id)
    {
        $course = Course::findOrFail($id);
        $recipes = $course->recipes()->with('images')->get();

        return response()->json([
            'success' => true,
            'message' => 'Course recipes retrieved successfully',
            'data' => $recipes
        ], 200);
    }

    public function addRecipe(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('edit courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'recipe_id' => 'required|exists:recipes,id',
            'day_number' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $course = Course::findOrFail($id);
        $recipeId = $request->recipe_id;
        
        // Check if recipe is already attached
        if ($course->recipes()->where('recipe_id', $recipeId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Recipe is already attached to this course',
            ], 400);
        }

        $course->recipes()->attach($recipeId, [
            'day_number' => $request->day_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recipe added to course successfully',
        ], 200);
    }

    public function getStudents($id)
    {
        $course = Course::findOrFail($id);
        $students = $course->students()->with(['user'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Course students retrieved successfully',
            'data' => $students
        ], 200);
    }

    public function getAttendance($id)
    {
        $course = Course::findOrFail($id);
        
        $registrations = $course->registrations()->with([
            'student', 
            'attendanceRecords' => function($query) {
                $query->orderBy('date', 'desc');
            }
        ])->get();

        $attendance = $registrations->map(function($registration) {
            return [
                'student' => $registration->student,
                'attendance_records' => $registration->attendanceRecords
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Course attendance retrieved successfully',
            'data' => $attendance
        ], 200);
    }

    public function publish(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('publish courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $course = Course::findOrFail($id);

        if ($course->status !== 'upcoming') {
            return response()->json([
                'success' => false,
                'message' => 'Only upcoming courses can be published',
            ], 400);
        }

        $course->status = 'active';
        $course->save();

        return response()->json([
            'success' => true,
            'message' => 'Course published successfully',
            'data' => $course
        ], 200);
    }

    public function cancel(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('cancel courses')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $course = Course::findOrFail($id);

        if ($course->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed courses cannot be canceled',
            ], 400);
        }

        $course->status = 'canceled';
        $course->save();

        // Here you would typically send notifications to all enrolled students

        return response()->json([
            'success' => true,
            'message' => 'Course canceled successfully',
            'data' => $course
        ], 200);
    }

    public function checkAvailability($id)
    {
        $course = Course::findOrFail($id);
        
        $availableSeats = $course->maximum_capacity - $course->current_enrollment;
        $isAvailable = ($availableSeats > 0) && in_array($course->status, ['upcoming', 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Course availability retrieved successfully',
            'data' => [
                'course_id' => $course->id,
                'available_seats' => $availableSeats,
                'is_available' => $isAvailable,
                'status' => $course->status
            ]
        ], 200);
    }
}