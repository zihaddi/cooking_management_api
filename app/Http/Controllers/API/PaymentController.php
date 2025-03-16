<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['webhook']);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'registration_id' => 'required|exists:registrations,id',
            'transaction_id' => 'required|string|max:100|unique:payments',
            'payment_date' => 'required|date',
            'payment_proof' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $registration = Registration::findOrFail($request->registration_id);
        
        // Check if the authenticated user is the student or has permission to create payments
        $student = Student::where('user_id', auth()->id())->first();
        if (($student && $student->id !== $registration->student_id) && 
            !auth()->user()->can('create payments')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Handle payment proof upload
        $image = $request->file('payment_proof');
        $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
        $paymentProofPath = $image->storeAs('payments/proofs', $filename, 'public');

        // Create payment
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'amount' => $registration->course->price,
            'payment_method' => 'Bkash',
            'transaction_id' => $request->transaction_id,
            'payment_date' => $request->payment_date,
            'payment_proof' => $paymentProofPath,
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment submitted successfully, awaiting verification',
            'data' => $payment
        ], 201);
    }

    public function show($id)
    {
        $payment = Payment::with('registration.student', 'registration.course')->findOrFail($id);
        
        // Check if the authenticated user is the student who made the payment or has permission to view payments
        $student = Student::where('user_id', auth()->id())->first();
        if (($student && $student->id !== $payment->registration->student_id) && 
            !auth()->user()->can('view payments')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully',
            'data' => $payment
        ], 200);
    }

    public function verify(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('verify payments')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = Payment::findOrFail($id);
        
        // Update payment status
        $payment->verification_status = $request->status;
        if ($request->status === 'rejected') {
            $payment->rejection_reason = $request->rejection_reason;
        }
        $payment->save();

        // If payment is verified, update registration status
        if ($request->status === 'verified') {
            $registration = $payment->registration;
            $registration->payment_status = 'completed';
            $registration->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment ' . $request->status . ' successfully',
            'data' => $payment
        ], 200);
    }

    public function webhook(Request $request)
    {
        // This endpoint would handle callbacks from payment gateway
        // For this example, we'll just log the request
        \Log::info('Payment webhook received', ['data' => $request->all()]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
        ], 200);
    }

    public function report(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('view payment reports')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = Payment::with('registration.student', 'registration.course');

        // Filter by verification status if provided
        if ($request->has('status')) {
            $query->where('verification_status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }

        // Filter by course if provided
        if ($request->has('course_id')) {
            $query->whereHas('registration', function($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        $payments = $query->paginate(15);

        // Calculate totals
        $totalAmount = $query->sum('amount');
        $verifiedAmount = $query->where('verification_status', 'verified')->sum('amount');
        $pendingAmount = $query->where('verification_status', 'pending')->sum('amount');
        $rejectedAmount = $query->where('verification_status', 'rejected')->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Payment report generated successfully',
            'data' => [
                'payments' => $payments,
                'summary' => [
                    'total_amount' => $totalAmount,
                    'verified_amount' => $verifiedAmount,
                    'pending_amount' => $pendingAmount,
                    'rejected_amount' => $rejectedAmount,
                ]
            ]
        ], 200);
    }
}