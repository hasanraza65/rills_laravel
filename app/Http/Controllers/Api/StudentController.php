<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Student;
use App\Models\StudentFeeHead;


class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::with(['class', 'section', 'parent'])
            ->where('branch_id', $request->branch_id);

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required',
            'admission_date' => 'nullable|date',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'name' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'nationality' => 'nullable|string',
            'address' => 'nullable|string',
            'home_contact' => 'nullable|string',
            'currently_studying' => 'nullable|string',
            'class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'previous_schools' => 'nullable|array',
            'health_issues' => 'nullable|array',
            'health_details' => 'nullable|string',
            'parent_id' => 'nullable|exists:parent_profiles,id',
            'source' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'is_active' => 'nullable|boolean',

            // ✅ NEW
            'fee_heads' => 'nullable|array',
            'fee_heads.*.head_name' => 'required|string',
            'fee_heads.*.head_amount' => 'required|numeric',
            'fee_heads.*.head_frequency' => 'required|string',
        ]);

        $data['added_by'] = auth()->id();
        $data['admission_no'] = $data['admission_no'] ?? 'ADM' . time();
        $data['is_active'] = $data['is_active'] ?? true;

        // photo — generate the stored filename ourselves; never trust the client's
        // original filename (path traversal / filename injection risk).
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('students/photos'), $filename);
            $data['photo'] = 'students/photos/' . $filename;
        }

        // attachments
        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('students/attachments'), $filename);
                $attachments[] = 'students/attachments/' . $filename;
            }
            $data['attachments'] = $attachments;
        }

        // ✅ Create student
        $student = Student::create($data);

        // ✅ Store fee heads
        if ($request->has('fee_heads')) {
            foreach ($request->fee_heads as $head) {
                $student->feeHeads()->create([
                    'head_name' => $head['head_name'],
                    'head_amount' => $head['head_amount'],
                    'head_frequency' => $head['head_frequency'],
                ]);
            }
        }

        return $student->load('feeHeads');
    }



    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $data = $request->validate([
            'admission_date' => 'nullable|date',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'name' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'nationality' => 'nullable|string',
            'address' => 'nullable|string',
            'home_contact' => 'nullable|string',
            'currently_studying' => 'nullable|string',
            'class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'previous_schools' => 'nullable|array',
            'health_issues' => 'nullable|array',
            'health_details' => 'nullable|string',
            'parent_id' => 'nullable|exists:parent_profiles,id',
            'source' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        // Handle photo replacement
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('students/photos'), $filename);
            $data['photo'] = 'students/photos/' . $filename;
        }

        // Handle additional attachments
        if ($request->hasFile('attachments')) {
            $attachments = $student->attachments ?? [];
            foreach ($request->file('attachments') as $file) {
                $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('students/attachments'), $filename);
                $attachments[] = 'students/attachments/' . $filename;
            }
            $data['attachments'] = $attachments;
        }

        $student->update($data);

        return $student;
    }

    public function show($id)
    {
        return Student::with(['class', 'section', 'parent', 'siblings'])->findOrFail($id);
    }

    public function destroy($id)
    {
        Student::findOrFail($id)->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function studentsByParent($id, Request $request){

         return Student::with(['class', 'section', 'parent'])
            ->where('branch_id', $request->branch_id)
            ->where('parent_id',$id)
            ->get();

    }
}
