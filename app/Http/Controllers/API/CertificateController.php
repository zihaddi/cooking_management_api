<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDF;

class CertificateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['verify']);
    }

    public function generate(Request $request, $registrationId)
    {
        // Check if user has permission
        if (!$request->user()->can('generate certificates')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $registration = Registration::with(['student', 'course'])->findOrFail($registrationId);
        
        // Check if course is completed
        if ($registration->course->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate certificate for a course that is not completed',
            ], 400);
        }
        
        // Check if registration has completed payment
        if ($registration->payment_status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate certificate for a registration with incomplete payment',
            ], 400);
        }
        
        // Check if certificate already exists
        if ($registration->certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate already exists for this registration',
                'data' => $registration->certificate
            ], 400);
        }

        // Generate certificate number
        $certificateNumber = 'CERT-' . now()->format('Y') . '-' . str_pad($registration->id, 5, '0', STR_PAD_LEFT);

        // Generate digital signature (in a real app, this would be more sophisticated)
        $digitalSignature = hash('sha256', $registration->id . $certificateNumber . now()->timestamp);

        // Generate PDF certificate
        // Note: In a real project, you would use a PDF library like DOMPDF
        // For this example, we'll pretend we're generating a PDF
        $pdfPath = 'certificates/' . $certificateNumber . '.pdf';
        
        // In a real project, this would be:
        // $pdf = PDF::loadView('certificates.template', [
        //     'student' => $registration->student,
        //     'course' => $registration->course,
        //     'certificate_number' => $certificateNumber,
        //     'issue_date' => now(),
        // ]);
        // Storage::disk('public')->put($pdfPath, $pdf->output());

        // Create certificate record
        $certificate = Certificate::create([
            'registration_id' => $registration->id,
            'certificate_number' => $certificateNumber,
            'issue_date' => now(),
            'digital_signature' => $digitalSignature,
            'pdf_path' => $pdfPath,
        ]);

        // Update registration certificate status
        $registration->certificate_status = 'issued';
        $registration->save();

        return response()->json([
            'success' => true,
            'message' => 'Certificate generated successfully',
            'data' => $certificate
        ], 201);
    }

    public function show($id)
    {
        $certificate = Certificate::with(['registration.student', 'registration.course'])->findOrFail($id);
        
        // Check if the authenticated user is the student or has permission to view certificates
        $student = Student::where('user_id', auth()->id())->first();
        if (($student && $student->id !== $certificate->registration->student_id) && 
            !auth()->user()->can('view certificates')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificate retrieved successfully',
            'data' => $certificate
        ], 200);
    }

    public function verify($certificateNumber)
    {
        $certificate = Certificate::where('certificate_number', $certificateNumber)
            ->with(['registration.student', 'registration.course'])
            ->first();
        
        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid certificate number',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificate verified successfully',
            'data' => [
                'is_valid' => true,
                'certificate_number' => $certificate->certificate_number,
                'issue_date' => $certificate->issue_date,
                'student_name' => $certificate->registration->student->name,
                'course_name' => $certificate->registration->course->title_en,
                'course_period' => $certificate->registration->course->start_date . ' to ' . $certificate->registration->course->end_date,
            ]
        ], 200);
    }
}