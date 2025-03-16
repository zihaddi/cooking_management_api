<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['checkAvailability']);
    }

    public function checkAvailability($courseId)
    {
        $course = Course::findOrFail($courseId);
        
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

    public function register(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Check course availability
        $availableSeats = $course->maximum_capacity - $course->current_enrollment;
        $isAvailable = ($availableSeats > 0) && in_array($course->status, ['upcoming', 'active']);

        if (!$isAvailable) {
            return response()->json([
                'success' => false,
                'message' => 'Course is not available for registration',
            ], 400);
        }

        // Get the authenticated user's student record
        $student = Student::where('user_id', auth()->id())->first();

        // If the user is an admin registering on behalf of a student
        if (!$student && auth()->user()->can('create registrations')) {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::findOrFail($request->student_id);
        }

        // Check if student is already registered for this course
        $existingRegistration = Registration::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'success' => false,
                'message' => 'Student is already registered for this course',
            ], 400);
        }

        // Create registration
        $registration = Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'payment_status' => 'pending',
            'certificate_status' => 'not_eligible',
        ]);

        // Increment course enrollment count
        $course->current_enrollment += 1;
        $course->save();

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'registration' => $registration,
                'payment_instructions' => [
                    'bkash_number' => '01XXXXXXXXX',
                    'amount' => $course->price,
                    'reference' => 'COOK-' . $registration->id
                ]
            ]
        ], 201);
    }

    public function verify(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('verify registrations')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $registration = Registration::findOrFail($id);

        // Update registration status
        $registration->payment_status = 'completed';
        $registration->save();

        return response()->json([
            'success' => true,
            'message' => 'Registration verified successfully',
            'data' => $registration
        ], 200);
    }

    public function cancel(Request $request, $id)
    {
        $registration = Registration::findOrFail($id);

        // Check if the authenticated user is the student or has permission to cancel registrations
        $student = Student::where('user_id', auth()->id())->first();
        if (($student && $student->id !== $registration->student_id) && 
            !auth()->user()->can('cancel registrations')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if the course has already started
        $course = $registration->course;
        if ($course->status === 'active' && now()->gt($course->start_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel registration for an active course that has already started',
            ], 400);
        }

        // Update course enrollment count
        $course->current_enrollment -= 1;
        $course->save();

        // Soft delete the registration
        $registration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registration canceled successfully',
        ], 200);
    }
}