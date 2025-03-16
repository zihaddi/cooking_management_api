<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin|super-admin|course-administrator');
    }

    public function stats(Request $request)
    {
        $stats = [
            'courses' => [
                'total' => Course::count(),
                'upcoming' => Course::where('status', 'upcoming')->count(),
                'active' => Course::where('status', 'active')->count(),
                'completed' => Course::where('status', 'completed')->count(),
                'canceled' => Course::where('status', 'canceled')->count(),
            ],
            'students' => [
                'total' => \App\Models\Student::count(),
                'new_this_month' => \App\Models\Student::whereMonth('registration_date', now()->month)
                    ->whereYear('registration_date', now()->year)
                    ->count(),
            ],
            'registrations' => [
                'total' => Registration::count(),
                'pending_payment' => Registration::where('payment_status', 'pending')->count(),
                'completed' => Registration::where('payment_status', 'completed')->count(),
            ],
            'payments' => [
                'total_amount' => Payment::where('verification_status', 'verified')->sum('amount'),
                'pending_verification' => Payment::where('verification_status', 'pending')->count(),
                'verified' => Payment::where('verification_status', 'verified')->count(),
                'rejected' => Payment::where('verification_status', 'rejected')->count(),
            ],
            'certificates' => [
                'total_issued' => \App\Models\Certificate::count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard stats retrieved successfully',
            'data' => $stats,
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()->name,
        ], 200);
    }

    public function upcomingCourses()
    {
        $courses = Course::where('status', 'upcoming')
            ->with('instructors')
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Upcoming courses retrieved successfully',
            'data' => $courses,
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()->name,
        ], 200);
    }

    public function activeCourses()
    {
        $courses = Course::where('status', 'active')
            ->with(['instructors', 'registrations'])
            ->orderBy('end_date')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Active courses retrieved successfully',
            'data' => $courses,
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()->name,
        ], 200);
    }

    public function pendingPayments()
    {
        $payments = Payment::where('verification_status', 'pending')
            ->with(['registration.student', 'registration.course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Pending payments retrieved successfully',
            'data' => $payments,
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()->name,
        ], 200);
    }

    public function newRegistrations()
    {
        $registrations = Registration::with(['student', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'New registrations retrieved successfully',
            'data' => $registrations,
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()->name,
        ], 200);
    }
}