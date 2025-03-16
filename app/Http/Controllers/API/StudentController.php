<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('view students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = Student::query();

        // Search by name or email if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $students = $query->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => $students
        ], 200);
    }

    public function store(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('create students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:students',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user account first
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        
        // Assign student role
        $user->assignRole('student');

        // Handle profile image upload
        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $profileImagePath = $image->storeAs('students/images', $filename, 'public');
        }

        $student = Student::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'profile_image' => $profileImagePath,
            'registration_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student created successfully',
            'data' => $student
        ], 201);
    }

    public function show($id)
    {
        $student = Student::with(['user', 'registrations.course'])->findOrFail($id);

        // Check if the authenticated user is the student or has permission to view students
        if ($student->user_id !== auth()->id() && !auth()->user()->can('view students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Student retrieved successfully',
            'data' => $student
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        // Check if the authenticated user is the student or has permission to edit students
        if ($student->user_id !== auth()->id() && !auth()->user()->can('edit students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'address' => 'sometimes|required|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($student->profile_image) {
                Storage::disk('public')->delete($student->profile_image);
            }

            $image = $request->file('profile_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $profileImagePath = $image->storeAs('students/images', $filename, 'public');
            
            $student->profile_image = $profileImagePath;
        }

        // Update student data
        $student->fill($request->only([
            'name', 'phone', 'address'
        ]));
        
        $student->save();

        // Update user data if needed
        if ($request->has('name')) {
            $student->user()->update(['name' => $request->name]);
        }

        // Update password if provided
        if ($request->filled('password')) {
            $user = $student->user;
            
            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }
            
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Student updated successfully',
            'data' => $student
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('delete students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $student = Student::findOrFail($id);
        
        // Check if student has any active registrations
        $activeRegistrations = $student->registrations()
            ->whereHas('course', function($query) {
                $query->whereIn('status', ['upcoming', 'active']);
            })
            ->count();
            
        if ($activeRegistrations > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete student with active course registrations',
            ], 400);
        }

        // Delete profile image if exists
        if ($student->profile_image) {
            Storage::disk('public')->delete($student->profile_image);
        }
        
        // Get the user to delete later
        $user = $student->user;
        
        // Soft delete the student
        $student->delete();
        
        // Delete the user account
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student deleted successfully',
        ], 200);
    }

    public function getCourses($id)
    {
        $student = Student::findOrFail($id);

        // Check if the authenticated user is the student or has permission to view students
        if ($student->user_id !== auth()->id() && !auth()->user()->can('view students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $courses = $student->courses()->with('instructors')->get();

        return response()->json([
            'success' => true,
            'message' => 'Student courses retrieved successfully',
            'data' => $courses
        ], 200);
    }

    public function getCertificates($id)
    {
        $student = Student::findOrFail($id);

        // Check if the authenticated user is the student or has permission to view students
        if ($student->user_id !== auth()->id() && !auth()->user()->can('view students')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $certificates = $student->certificates()->with('registration.course')->get();

        return response()->json([
            'success' => true,
            'message' => 'Student certificates retrieved successfully',
            'data' => $certificates
        ], 200);
    }
}