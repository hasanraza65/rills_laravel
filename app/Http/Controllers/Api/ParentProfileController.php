<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ParentProfile;

class ParentProfileController extends Controller
{
    public function index(Request $request)
    {
        return ParentProfile::with(['students', 'user'])->where('branch_id', $request->branch_id)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'father_name' => 'nullable',
            'father_cnic' => 'nullable',
            'father_education' => 'nullable',
            'father_occupation' => 'nullable',
            'father_contact_no' => 'nullable',

            'mother_name' => 'nullable',
            'mother_cnic' => 'nullable',
            'mother_education' => 'nullable',
            'mother_occupation' => 'nullable',
            'mother_contact_no' => 'nullable',

            // guardian fields
            'guardian_name' => 'nullable',
            'guardian_cnic' => 'nullable',
            'guardian_education' => 'nullable',
            'guardian_occupation' => 'nullable',
            'guardian_contact_no' => 'nullable',

            'address' => 'nullable',
            'guardian_type' => 'nullable|in:father,mother,other'
        ]);

        $guardianType = $data['guardian_type'] ?? 'father';

        // ✅ DEFAULT VALUES
        $phone = null;
        $name = null;
        $cnic = null;

        // =========================
        // 1. Father / Mother logic
        // =========================
        if ($guardianType === 'father') {

            $phone = $data['father_contact_no'] ?? null;
            $name = $data['father_name'] ?? 'Parent';
            $cnic = $data['father_cnic'] ?? null;

        } elseif ($guardianType === 'mother') {

            $phone = $data['mother_contact_no'] ?? null;
            $name = $data['mother_name'] ?? 'Parent';
            $cnic = $data['mother_cnic'] ?? null;

        }

        // =========================
        // 2. Other Guardian logic
        // =========================
        elseif ($guardianType === 'other') {

            $phone = $data['guardian_contact_no'] ?? null;
            $name = $data['guardian_name'] ?? 'Guardian';
            $cnic = $data['guardian_cnic'] ?? null;
        }

        // ⚠️ Safety check
        if (!$phone) {
            return response()->json(['error' => 'Phone number is required'], 422);
        }

        // =========================
        // Create or get user
        // =========================
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $email = $phone . '@rills.edu.pk';
            $password = Str::random(8);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'user_role' => 5,
                'cnic' => $cnic,
                'phone' => $phone,
                'password' => Hash::make($password),
                'is_active' => 1,
            ]);
        }

        // =========================
        // Save parent profile
        // =========================
        $data['user_id'] = $user->id;
        $data['added_by'] = auth()->id();

        return ParentProfile::create($data);
    }

    public function show($id)
    {
        return ParentProfile::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $parent = ParentProfile::findOrFail($id);

        $data = $request->validate([
            'father_name' => 'nullable',
            'father_cnic' => 'nullable',
            'father_education' => 'nullable',
            'father_occupation' => 'nullable',
            'father_contact_no' => 'nullable',

            'mother_name' => 'nullable',
            'mother_cnic' => 'nullable',
            'mother_education' => 'nullable',
            'mother_occupation' => 'nullable',
            'mother_contact_no' => 'nullable',

            'guardian_name' => 'nullable',
            'guardian_cnic' => 'nullable',
            'guardian_education' => 'nullable',
            'guardian_occupation' => 'nullable',
            'guardian_contact_no' => 'nullable',

            'address' => 'nullable',
            'guardian_type' => 'nullable|in:father,mother,other'
        ]);

        $guardianType = $data['guardian_type'] ?? $parent->guardian_type;

        $phone = null;
        $name = null;
        $cnic = null;

        // =========================
        // Father
        // =========================
        if ($guardianType === 'father') {

            $phone = $data['father_contact_no'] ?? $parent->father_contact_no;
            $name = $data['father_name'] ?? $parent->father_name;
            $cnic = $data['father_cnic'] ?? $parent->father_cnic;

        }
        // =========================
        // Mother
        // =========================
        elseif ($guardianType === 'mother') {

            $phone = $data['mother_contact_no'] ?? $parent->mother_contact_no;
            $name = $data['mother_name'] ?? $parent->mother_name;
            $cnic = $data['mother_cnic'] ?? $parent->mother_cnic;

        }
        // =========================
        // Other Guardian
        // =========================
        elseif ($guardianType === 'other') {

            $phone = $data['guardian_contact_no'] ?? null;
            $name = $data['guardian_name'] ?? null;
            $cnic = $data['guardian_cnic'] ?? null;
        }

        // ⚠️ Ensure phone exists
        if (!$phone) {
            return response()->json([
                'error' => 'Phone number is required'
            ], 422);
        }

        // =========================
        // Update / create user
        // =========================
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $email = $phone . '@rills.edu.pk';
            $password = Str::random(8);

            $user = User::create([
                'name' => $name ?? 'Parent',
                'email' => $email,
                'user_role' => 5,
                'cnic' => $cnic,
                'phone' => $phone,
                'password' => Hash::make($password),
                'is_active' => 1,
            ]);
        } else {
            $user->update([
                'name' => $name ?? $user->name,
                'cnic' => $cnic ?? $user->cnic,
            ]);
        }

        // =========================
        // Update parent profile
        // =========================
        $parent->update(array_merge($data, [
            'user_id' => $user->id,
            'guardian_type' => $guardianType
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully',
            'data' => $parent
        ]);
    }

    public function destroy($id)
    {
        ParentProfile::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Parent deleted'
        ]);
    }
}