<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Diary;
use App\Models\ParentProfile;
use App\Models\Student;
use Illuminate\Http\Request;

class DiaryController extends Controller
{
    private const ROLE_SUPER_ADMIN  = 1;
    private const ROLE_SCHOOL_ADMIN = 2;
    private const ROLE_BRANCH_ADMIN = 3;
    private const ROLE_TEACHER      = 4;
    private const ROLE_PARENT       = 5;

    private const EAGER = ['classSubject.class:id,name', 'classSubject.section:id,name'];

    /**
     * List diaries, scoped by the caller's role:
     *   Teacher — only the subjects they are assigned to teach.
     *   Parent  — only the sections their children are enrolled in.
     *   Admin   — everything in the branch.
     *
     * Optional filters: section_id, class_subject_id, date, topic.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id'        => ['nullable', 'integer', 'exists:branches,id'],
            'section_id'       => ['nullable', 'integer', 'exists:sections,id'],
            'class_subject_id' => ['nullable', 'integer', 'exists:class_subjects,id'],
            'date'             => ['nullable', 'date_format:Y-m-d'],
            'topic'            => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $role = (int) $user->user_role;

        $query = Diary::with(self::EAGER);

        if ($role === self::ROLE_TEACHER) {
            // The old check read `if (!auth()->user()->user_role == 5)`, which PHP
            // parses as `(!$role) == 5` — always false. Teacher scoping never ran,
            // so every user saw every diary in the branch.
            $query->whereIn('class_subject_id', $this->teacherSubjectIds((int) $user->id));
        } elseif ($role === self::ROLE_PARENT) {
            $query->whereIn('class_subject_id', $this->parentSubjectIds((int) $user->id));
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
            $query->whereHas('classSubject', fn ($q) => $q->where('section_id', $request->section_id));
        }

        if ($request->filled('class_subject_id')) {
            $query->where('class_subject_id', $request->class_subject_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('topic')) {
            $query->where('topic', 'LIKE', '%' . $request->topic . '%');
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('date')->latest('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_subject_id' => ['required', 'integer', 'exists:class_subjects,id'],
            'topic'            => ['required', 'string', 'max:255'],
            'date'             => ['required', 'date_format:Y-m-d'],
            'page_number'      => ['nullable', 'string', 'max:255'],
            'resources'        => ['nullable', 'string', 'max:255'],
            'link'             => ['nullable', 'string', 'max:255'],
            'activity'         => ['nullable', 'string'],
            'home_work'        => ['nullable', 'string'],
            'status'           => ['nullable', 'string', 'in:Pending,Approved'],
        ]);

        $subject = ClassSubject::find($validated['class_subject_id']);

        if (! $this->canWriteSubject($subject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this subject.',
            ], 403);
        }

        $exists = Diary::where('class_subject_id', $subject->id)
            ->whereDate('date', $validated['date'])
            ->exists();

        if ($exists) {
            // 422, not a 200 with success:false — a 200 is invisible to the client's
            // error handling, so the old code reported success and created nothing.
            return response()->json([
                'success' => false,
                'message' => 'A diary already exists for this subject on this date.',
            ], 422);
        }

        $diary = Diary::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'Pending',
            // Derived from the subject. Never trust a client-supplied branch_id —
            // the old code mass-assigned $request->all().
            'branch_id' => $subject->branch_id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Diary created successfully',
            'data'    => $diary->load(self::EAGER),
        ], 201);
    }

    public function show($id)
    {
        $diary = Diary::with(self::EAGER)->find($id);

        if (! $diary) {
            return response()->json(['success' => false, 'message' => 'Diary not found'], 404);
        }

        if (! $this->canReadDiary($diary)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this diary.',
            ], 403);
        }

        return response()->json(['success' => true, 'data' => $diary]);
    }

    /**
     * Accepts every editable field. The old update() took `$request->all()` with no
     * validation, and the client only ever sent topic/description/date/status — so an
     * edit silently dropped page_number, resources, link and home_work.
     */
    public function update(Request $request, $id)
    {
        $diary = Diary::find($id);

        if (! $diary) {
            return response()->json(['success' => false, 'message' => 'Diary not found'], 404);
        }

        if (! $this->canWriteSubject($diary->classSubject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to edit this diary.',
            ], 403);
        }

        $validated = $request->validate([
            'class_subject_id' => ['sometimes', 'integer', 'exists:class_subjects,id'],
            'topic'            => ['sometimes', 'required', 'string', 'max:255'],
            'date'             => ['sometimes', 'required', 'date_format:Y-m-d'],
            'page_number'      => ['nullable', 'string', 'max:255'],
            'resources'        => ['nullable', 'string', 'max:255'],
            'link'             => ['nullable', 'string', 'max:255'],
            'activity'         => ['nullable', 'string'],
            'home_work'        => ['nullable', 'string'],
            'status'           => ['sometimes', 'string', 'in:Pending,Approved'],
        ]);

        // Moving a diary to a different subject re-checks ownership and re-derives the branch.
        if (array_key_exists('class_subject_id', $validated)) {
            $target = ClassSubject::find($validated['class_subject_id']);

            if (! $this->canWriteSubject($target)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this subject.',
                ], 403);
            }

            $validated['branch_id'] = $target->branch_id;
        }

        $diary->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Diary updated successfully',
            'data'    => $diary->fresh(self::EAGER),
        ]);
    }

    public function destroy($id)
    {
        $diary = Diary::find($id);

        if (! $diary) {
            return response()->json(['success' => false, 'message' => 'Diary not found'], 404);
        }

        if (! $this->canWriteSubject($diary->classSubject)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete this diary.',
            ], 403);
        }

        $diary->delete();

        return response()->json(['success' => true, 'message' => 'Diary deleted successfully']);
    }

    /**
     * Narrow endpoint so an approver can flip status without rewriting the diary body.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'string', 'in:Pending,Approved'],
        ]);

        $diary = Diary::find($id);

        if (! $diary) {
            return response()->json(['success' => false, 'message' => 'Diary not found'], 404);
        }

        $role = (int) auth()->user()->user_role;

        $canApprove = in_array($role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SCHOOL_ADMIN,
            self::ROLE_BRANCH_ADMIN,
        ], true) || $this->canWriteSubject($diary->classSubject);

        if (! $canApprove) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to change this diary\'s status.',
            ], 403);
        }

        $diary->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'data'    => $diary->fresh(self::EAGER),
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
     * Teachers may only write diaries for subjects they are assigned to.
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

    private function canReadDiary(Diary $diary): bool
    {
        $user = auth()->user();
        $role = (int) $user->user_role;

        if ($role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($role === self::ROLE_TEACHER) {
            return (int) $diary->classSubject?->teacher_id === (int) $user->id;
        }

        if ($role === self::ROLE_PARENT) {
            return $this->parentSubjectIds((int) $user->id)->contains($diary->class_subject_id);
        }

        return $user->branch_id !== null
            && (int) $diary->branch_id === (int) $user->branch_id;
    }
}
