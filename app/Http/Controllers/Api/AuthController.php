<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\TempAddKey;
use App\Models\ParentProfile;

class AuthController extends Controller
{

    public function checkTempKey(Request $request)
    {
        $request->validate([
            'key' => 'required|string'
        ]);

        $exists = TempAddKey::where('key', $request->key)->exists();

        if ($exists) {
            return response()->json([
                'status' => true,
                'message' => 'Key is valid'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid key'
        ], 404);
    }

    public function register(Request $request)
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

            'address' => 'nullable',
            'guardian_type' => 'nullable|in:father,mother'
        ]);

        // ✅ Decide guardian (ONLY for user creation)
        $guardianType = $data['guardian_type'] ?? 'father';

        if ($guardianType === 'mother') {
            $phone = $data['mother_contact_no'] ?? null;
            $name = $data['mother_name'] ?? 'Parent';
            $cnic = $data['mother_cnic'] ?? null;
        } else {
            $phone = $data['father_contact_no'] ?? null;
            $name = $data['father_name'] ?? 'Parent';
            $cnic = $data['father_cnic'] ?? null;
        }

        // ❌ Must have phone
        if (!$phone) {
            return response()->json([
                'status' => false,
                'message' => 'Phone number is required'
            ], 422);
        }

        // ✅ Check existing user
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $email = $phone . '@rills.edu.pk';
            $plainPassword = Str::random(8);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'user_role' => 5,
                'cnic' => $cnic,
                'phone' => $phone,
                'password' => Hash::make($plainPassword),
                'is_active' => 1,
            ]);
        }

        // ✅ IMPORTANT: Insert BOTH parents data
        $profileData = [
            'user_id' => $user->id,
            'added_by' => $user->id,

            // Father
            'father_name' => $data['father_name'] ?? null,
            'father_cnic' => $data['father_cnic'] ?? null,
            'father_education' => $data['father_education'] ?? null,
            'father_occupation' => $data['father_occupation'] ?? null,
            'father_contact_no' => $data['father_contact_no'] ?? null,

            // Mother
            'mother_name' => $data['mother_name'] ?? null,
            'mother_cnic' => $data['mother_cnic'] ?? null,
            'mother_education' => $data['mother_education'] ?? null,
            'mother_occupation' => $data['mother_occupation'] ?? null,
            'mother_contact_no' => $data['mother_contact_no'] ?? null,

            'address' => $data['address'] ?? null,
            'guardian_type' => $guardianType,
        ];

        $profile = ParentProfile::create($profileData);

        // ✅ Token
        $token = $user->createToken('auth_token')->plainTextToken;

        TempAddKey::where('key',$phone)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Parent registered successfully',
            'user' => $user,
            'profile' => $profile,
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        $credentials = [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ];

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user  = $this->withBranches(Auth::user());
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function user(Request $request)
    {
        return response()->json($this->withBranches($request->user()));
    }

    private function withBranches(User $user): User
    {
        switch ((int) $user->user_role) {
            case 1: // Super Admin — sees every branch
                $user->setRelation('branches', Branch::orderBy('branch_name')->get());
                break;
            case 3: // Branch Admin — sees only their single assigned branch
                $branch = $user->branch_id
                    ? Branch::where('id', $user->branch_id)->get()
                    : collect();
                $user->setRelation('branches', $branch);
                break;
            default:
                $user->setRelation('branches', collect());
        }
        return $user;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}