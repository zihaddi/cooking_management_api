<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstructorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Instructor::query();

        // Search by name or specialization if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        $instructors = $query->with('courses')->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Instructors retrieved successfully',
            'data' => $instructors,
            'current_time' => '2025-03-16 15:04:40',
            'user' => 'zihaddi',
        ], 200);
    }

    public function store(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('create instructors')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:instructors',
            'phone' => 'required|string|max:20',
            'bio_en' => 'required|string',
            'bio_bn' => 'nullable|string',
            'specialization' => 'required|string|max:255',
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
        
        // Assign instructor role
        $user->assignRole('instructor');

        // Handle profile image upload
        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $profileImagePath = $image->storeAs('instructors/images', $filename, 'public');
        }

        $instructor = Instructor::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'bio_en' => $request->bio_en,
            'bio_bn' => $request->bio_bn,
            'specialization' => $request->specialization,
            'profile_image' => $profileImagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instructor created successfully',
            'data' => $instructor,
            'current_time' => '2025-03-16 15:04:40',
            'user' => 'zihaddi',
        ], 201);
    }

    public function show($id)
    {
        $instructor = Instructor::with('courses')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Instructor retrieved successfully',
            'data' => $instructor,
            'current_time' => '2025-03-16 15:04:40',
            'user' => 'zihaddi',
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);

        // Check if the authenticated user is the instructor or has permission to edit instructors
        if ($instructor->user_id !== auth()->id() && !auth()->user()->can('edit instructors')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'bio_en' => 'sometimes|required|string',
            'bio_bn' => 'nullable|string',
            'specialization' => 'sometimes|required|string|max:255',
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
            if ($instructor->profile_image) {
                Storage::disk('public')->delete($instructor->profile_image);
            }

            $image = $request->file('profile_image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $profileImagePath = $image->storeAs('instructors/images', $filename, 'public');
            
            $instructor->profile_image = $profileImagePath;
        }

        // Update instructor data
        $instructor->fill($request->only([
            'name', 'phone', 'bio_en', 'bio_bn', 'specialization'
        ]));
        
        $instructor->save();

        // Update user data if needed
        if ($request->has('name')) {
            $instructor->user()->update(['name' => $request->name]);
        }

        // Update password if provided
        if ($request->filled('password')) {
            $user = $instructor->user;
            
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
            'message' => 'Instructor updated successfully',
            'data' => $instructor,
            'current_time' => '2025-03-16 15:04:40',
            'user' => 'zihaddi',
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('delete instructors')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $instructor = Instructor::findOrFail($id);
        
        // Check if instructor has any active courses
        $activeCourses = $instructor->courses()
            ->whereIn('status', ['upcoming', 'active'])
            ->count();
            
        if ($activeCourses > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete instructor with active courses',
            ], 400);
        }

        // Delete profile image if exists
        if ($instructor->profile_image) {
            Storage::disk('public')->delete($instructor->profile_image);
        }
        
        // Get the user to delete later
        $user = $instructor->user;
        
        // Delete the instructor
        $instructor->delete();
        
        // Delete the user account
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Instructor deleted successfully',
            'current_time' => '2025-03-16 15:04:40',
            'user' => 'zihaddi',
        ], 200);
    }
}