<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /** Roles that count as staff (everyone except PARENT = 5). */
    const STAFF_ROLES = [1, 2, 3, 4, 6, 7];

    /**
     * List staff for a branch. Staff are users with a staff role.
     */
    public function index(Request $request)
    {
        $query = User::with(['staffProfile', 'role'])
            ->whereIn('user_role', self::STAFF_ROLES);

        // Branch scope: super admin (no branch filter) vs branch-scoped roles.
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('role_id')) {
            $query->where('user_role', $request->role_id);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'father_husband_name'  => 'nullable|string|max:150',
            'cnic'                 => ['nullable', 'string', 'max:20', Rule::unique('users', 'cnic')],
            'gender'               => 'nullable|in:male,female,other',
            'dob'                  => 'nullable|date',
            'date_of_joining'      => 'nullable|date',
            'marital_status'       => 'nullable|in:single,married,divorced,widowed',
            'contact_no'           => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')],
            'whatsapp_no'          => 'nullable|string|max:20',
            'emergency_contact_no' => 'nullable|string|max:20',
            'current_address'      => 'nullable|string',
            'permanent_address'    => 'nullable|string',
            'user_role'            => ['required', Rule::in(self::STAFF_ROLES)],
            'branch_id'            => 'nullable|exists:branches,id',
            'email'                => 'nullable|email|unique:users,email',
            'password'             => 'nullable|string|min:6',
        ], [
            'cnic.unique'       => 'This CNIC is already registered to another user.',
            'contact_no.unique' => 'This contact number is already registered to another user.',
        ]);

        $staff = DB::transaction(function () use ($data, $request) {
            // Derive login credentials. Email/password are auto-generated when not
            // supplied so the record is a valid, login-capable user.
            $email = $data['email'] ?? $this->generateEmail($data);
            $plainPassword = $data['password'] ?? ($data['cnic'] ?? $data['contact_no'] ?? 'password');

            $user = User::create([
                'name'      => $data['name'],
                'email'     => $email,
                'password'  => Hash::make($plainPassword),
                'user_role' => $data['user_role'],
                'branch_id' => $data['branch_id'] ?? null,
                'cnic'      => $data['cnic'] ?? null,
                'phone'     => $data['contact_no'] ?? null,
                'is_active' => 1,
            ]);

            $user->staffProfile()->create([
                'branch_id'            => $data['branch_id'] ?? null,
                'added_by'             => auth()->id(),
                'father_husband_name'  => $data['father_husband_name'] ?? null,
                'gender'               => $data['gender'] ?? null,
                'dob'                  => $data['dob'] ?? null,
                'date_of_joining'      => $data['date_of_joining'] ?? null,
                'marital_status'       => $data['marital_status'] ?? null,
                'whatsapp_no'          => $data['whatsapp_no'] ?? null,
                'emergency_contact_no' => $data['emergency_contact_no'] ?? null,
                'current_address'      => $data['current_address'] ?? null,
                'permanent_address'    => $data['permanent_address'] ?? null,
            ]);

            return $user->load(['staffProfile', 'role']);
        });

        return response()->json(['data' => $staff], 201);
    }

    public function show($id)
    {
        $staff = User::with(['staffProfile', 'role'])
            ->whereIn('user_role', self::STAFF_ROLES)
            ->findOrFail($id);

        return response()->json(['data' => $staff]);
    }

    public function update(Request $request, $id)
    {
        $user = User::whereIn('user_role', self::STAFF_ROLES)->findOrFail($id);

        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'father_husband_name'  => 'nullable|string|max:150',
            'cnic'                 => ['nullable', 'string', 'max:20', Rule::unique('users', 'cnic')->ignore($user->id)],
            'gender'               => 'nullable|in:male,female,other',
            'dob'                  => 'nullable|date',
            'date_of_joining'      => 'nullable|date',
            'marital_status'       => 'nullable|in:single,married,divorced,widowed',
            'contact_no'           => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'whatsapp_no'          => 'nullable|string|max:20',
            'emergency_contact_no' => 'nullable|string|max:20',
            'current_address'      => 'nullable|string',
            'permanent_address'    => 'nullable|string',
            'user_role'            => ['required', Rule::in(self::STAFF_ROLES)],
            'branch_id'            => 'nullable|exists:branches,id',
            'email'                => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'             => 'nullable|string|min:6',
        ], [
            'cnic.unique'       => 'This CNIC is already registered to another user.',
            'contact_no.unique' => 'This contact number is already registered to another user.',
        ]);

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'name'      => $data['name'],
                'user_role' => $data['user_role'],
                'branch_id' => $data['branch_id'] ?? null,
                'cnic'      => $data['cnic'] ?? null,
                'phone'     => $data['contact_no'] ?? null,
                'email'     => $data['email'] ?? $user->email,
            ]);

            if (!empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            $user->staffProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'branch_id'            => $data['branch_id'] ?? null,
                    'father_husband_name'  => $data['father_husband_name'] ?? null,
                    'gender'               => $data['gender'] ?? null,
                    'dob'                  => $data['dob'] ?? null,
                    'date_of_joining'      => $data['date_of_joining'] ?? null,
                    'marital_status'       => $data['marital_status'] ?? null,
                    'whatsapp_no'          => $data['whatsapp_no'] ?? null,
                    'emergency_contact_no' => $data['emergency_contact_no'] ?? null,
                    'current_address'      => $data['current_address'] ?? null,
                    'permanent_address'    => $data['permanent_address'] ?? null,
                ]
            );
        });

        return response()->json(['data' => $user->fresh(['staffProfile', 'role'])]);
    }

    public function destroy($id)
    {
        $user = User::whereIn('user_role', self::STAFF_ROLES)->findOrFail($id);
        $user->delete(); // staff_profiles row cascades on delete

        return response()->json(['message' => 'Staff member deleted.']);
    }

    /**
     * Build a unique login email from contact/cnic when none is provided.
     */
    private function generateEmail(array $data): string
    {
        $base = preg_replace('/\D/', '', $data['contact_no'] ?? $data['cnic'] ?? '');
        if ($base === '' || $base === null) {
            $base = 'staff' . time();
        }

        $email = $base . '@rills.edu.pk';
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = $base . '-' . $i++ . '@rills.edu.pk';
        }

        return $email;
    }
}
