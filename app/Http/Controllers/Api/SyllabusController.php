<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Syllabus;
use Illuminate\Http\Request;

class SyllabusController extends Controller
{
    private const ROLE_SUPER_ADMIN  = 1;
    private const ROLE_SCHOOL_ADMIN = 2;
    private const ROLE_BRANCH_ADMIN = 3;
    private const ROLE_TEACHER      = 4;
    private const ROLE_PARENT       = 5;

    private const EAGER = ['subject.class:id,name', 'subject.section:id,name'];

    /**
     * List syllabus entries, scoped by the caller's role:
     *   Teacher — only the subjects they are assigned to teach.
     *   Parent  — only the sections their children are enrolled in.
     *   Admin   — everything in the branch.
     *
     * Optional filters: section_id, subject_id, date.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id'  => ['nullable', 'integer', 'exists:branches,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:class_subjects,id'],
            'date'       => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = auth()->user();
        $role = (int) $user->user_role;

        $query = Syllabus::with(self::EAGER);

        if ($role === self::ROLE_TEACHER) {
            $query->whereIn('subject_id', $this->teacherSubjectIds((int) $user->id));
        } elseif ($role === self::ROLE_PARENT) {
            $query->whereIn('subject_id', $this->parentSubjectIds((int) $user->id));
        } else {
            // Admins are scoped by branch. Teachers and parents are already
            // constrained by subject, which implies their branch — and their user
            // row often has no branch_id at all, so don't filter them on it.
            $branchId = $request->branch_id ?: $user->branch_id;

            if ($branchId) {
                $query->where('branch_id', $branchId);
            } elseif ($role !== self::ROLE_SUPER_ADMIN) {
                // No branch context and not a super admin: show nothing, not everything.
                return response()->json(['success' => true, 'data' => []]);
            }
        }

        if ($request->filled('section_id')) {
            $query->whereHas('subject', fn ($q) => $q->where('section_id', $request->section_id));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('month', $request->date);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('month')->latest('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:class_subjects,id'],
            'month'      => ['required', 'date_format:Y-m-d'],
            'page'       => ['nullable', 'string', 'max:255'],
            'link'       => ['nullable', 'string', 'max:255'],
            'content'    => ['required', 'string'],
            'status'     => ['nullable', 'string', 'in:Pending,Approved'],
        ]);

        $subject = ClassSubject::find($validated['subject_id']);

        if (! $this->canWriteSubject($subject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this subject.',
            ], 403);
        }

        $exists = Syllabus::where('subject_id', $subject->id)
            ->whereDate('month', $validated['month'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This syllabus already exists.',
            ], 422);
        }

        $syllabus = Syllabus::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'Pending',
            // Derived from the subject. Never trust a client-supplied branch_id —
            // the old code inserted $request->branch_id directly with no validation.
            'branch_id' => $subject->branch_id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Syllabus created',
            'data'    => $syllabus->load(self::EAGER),
        ], 201);
    }

    public function show($id)
    {
        $syllabus = Syllabus::with(self::EAGER)->find($id);

        if (! $syllabus) {
            return response()->json(['success' => false, 'message' => 'Syllabus not found'], 404);
        }

        if (! $this->canReadSyllabus($syllabus)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this syllabus entry.',
            ], 403);
        }

        return response()->json(['success' => true, 'data' => $syllabus]);
    }

    /**
     * Accepts every editable field, so an edit is never lossy.
     */
    public function update(Request $request, $id)
    {
        $syllabus = Syllabus::find($id);

        if (! $syllabus) {
            return response()->json(['success' => false, 'message' => 'Syllabus not found'], 404);
        }

        if (! $this->canWriteSubject($syllabus->subject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to edit this syllabus entry.',
            ], 403);
        }

        $validated = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:class_subjects,id'],
            'month'      => ['sometimes', 'required', 'date_format:Y-m-d'],
            'page'       => ['nullable', 'string', 'max:255'],
            'link'       => ['nullable', 'string', 'max:255'],
            'content'    => ['sometimes', 'required', 'string'],
            'status'     => ['sometimes', 'string', 'in:Pending,Approved'],
        ]);

        // Moving a syllabus entry to a different subject re-checks ownership and
        // re-derives the branch.
        if (array_key_exists('subject_id', $validated)) {
            $target = ClassSubject::find($validated['subject_id']);

            if (! $this->canWriteSubject($target)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this subject.',
                ], 403);
            }

            $validated['branch_id'] = $target->branch_id;
        }

        $syllabus->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully',
            'data'    => $syllabus->fresh(self::EAGER),
        ]);
    }

    public function destroy($id)
    {
        $syllabus = Syllabus::find($id);

        if (! $syllabus) {
            return response()->json(['success' => false, 'message' => 'Syllabus not found'], 404);
        }

        if (! $this->canWriteSubject($syllabus->subject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete this syllabus entry.',
            ], 403);
        }

        $syllabus->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }

    /**
     * Narrow endpoint so an approver can flip status without rewriting the entry.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'string', 'in:Pending,Approved'],
        ]);

        $syllabus = Syllabus::find($id);

        if (! $syllabus) {
            return response()->json(['success' => false, 'message' => 'Syllabus not found'], 404);
        }

        $role = (int) auth()->user()->user_role;

        $canApprove = in_array($role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SCHOOL_ADMIN,
            self::ROLE_BRANCH_ADMIN,
        ], true) || $this->canWriteSubject($syllabus->subject);

        if (! $canApprove) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to change this entry\'s status.',
            ], 403);
        }

        $syllabus->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'data'    => $syllabus->fresh(self::EAGER),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** class_subject ids the teacher is assigned to. */
    private function teacherSubjectIds(int $userId)
    {
        return ClassSubject::where('teacher_id', $userId)->pluck('id');
    }

    /** class_subject ids for every section the parent's children sit in. */
    private function parentSubjectIds(int $userId)
    {
        $profileIds = ParentProfile::where('user_id', $userId)->pluck('id');

        if ($profileIds->isEmpty()) {
            return collect();
        }

        $sectionIds = Student::whereIn('parent_id', $profileIds)
            ->whereNotNull('section_id')
            ->pluck('section_id')
            ->unique();

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        return ClassSubject::whereIn('section_id', $sectionIds)->pluck('id');
    }

    /**
     * Teachers may only write syllabus entries for subjects they are assigned to.
     * Admins may write anything in their branch. Parents may never write.
     */
    private function canWriteSubject(?ClassSubject $subject): bool
    {
        if (! $subject) {
            return false;
        }

        $user = auth()->user();
        $role = (int) $user->user_role;

        if ($role === self::ROLE_TEACHER) {
            return (int) $subject->teacher_id === (int) $user->id;
        }

        if ($role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if (in_array($role, [self::ROLE_SCHOOL_ADMIN, self::ROLE_BRANCH_ADMIN], true)) {
            return $user->branch_id !== null
                && (int) $subject->branch_id === (int) $user->branch_id;
        }

        return false;
    }

    private function canReadSyllabus(Syllabus $syllabus): bool
    {
        $user = auth()->user();
        $role = (int) $user->user_role;

        if ($role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($role === self::ROLE_TEACHER) {
            return (int) $syllabus->subject?->teacher_id === (int) $user->id;
        }

        if ($role === self::ROLE_PARENT) {
            return $this->parentSubjectIds((int) $user->id)->contains($syllabus->subject_id);
        }

        return $user->branch_id !== null
            && (int) $syllabus->branch_id === (int) $user->branch_id;
    }
}
