<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonPlanSubject;
use App\Models\LessonPlanDoneTopic;
use App\Models\QbSubject;
use App\Models\QbTopic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonPlanSubjectController extends Controller
{
    /**
     * List all teachers (user_role = 4) with their assigned subject count
     * GET /api/lesson-plan/teachers?branch_id=1
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = User::where('user_role', 4)->with(['branch:id,branch_name']);

        if ($user->user_role !== 1) {
            $branchId = $request->branch_id ?? null;
            if ($branchId) $query->where('branch_id', $branchId);
        } elseif ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        $teachers = $query->get()->map(function ($teacher) {
            $teacher->assigned_subjects_count = LessonPlanSubject::where('user_id', $teacher->id)->count();
            return $teacher;
        });

        return response()->json(['success' => true, 'data' => $teachers]);
    }

    /**
     * Get subjects assigned to a specific teacher
     * GET /api/lesson-plan/teachers/{teacherId}/subjects
     */
    public function teacherSubjects($teacherId)
    {
        $teacher = User::find($teacherId);
        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Teacher not found.'], 404);
        }

        $assignedSubjects = LessonPlanSubject::where('user_id', $teacherId)
            ->with(['subject.schoolClass:id,name', 'subject:id,name,class_id'])
            ->get();

        $allSubjects = QbSubject::with('schoolClass:id,name')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'teacher'          => $teacher->only(['id', 'name', 'email', 'phone']),
                'assigned_subjects' => $assignedSubjects,
                'all_subjects'     => $allSubjects,
            ],
        ]);
    }

    /**
     * Get topics for each subject assigned to a teacher — with done status
     * GET /api/lesson-plan/teachers/{teacherId}/topics
     */
    public function teacherTopics($teacherId)
    {
        $teacher = User::find($teacherId);
        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Teacher not found.'], 404);
        }

        $assignedSubjectIds = LessonPlanSubject::where('user_id', $teacherId)->pluck('subject_id');

        $subjects = QbSubject::with(['schoolClass:id,name'])
            ->whereIn('id', $assignedSubjectIds)
            ->get()
            ->map(function ($subject) use ($teacherId) {
                $topics = QbTopic::where('subject_id', $subject->id)
                    ->with(['objectives', 'attachments'])
                    ->get()
                    ->map(function ($topic) use ($teacherId, $subject) {
                        $done = LessonPlanDoneTopic::where('topic_id', $topic->id)
                            ->where('teacher_id', $teacherId)
                            ->where('subject_id', $subject->id)
                            ->first();
                        $topic->is_done        = (bool) $done;
                        $topic->completed_date = $done ? $done->completed_date : null;
                        return $topic;
                    });

                $subject->topics       = $topics;
                $subject->total_topics = $topics->count();
                $subject->done_topics  = $topics->where('is_done', true)->count();
                return $subject;
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'teacher'  => $teacher->only(['id', 'name', 'email']),
                'subjects' => $subjects,
            ],
        ]);
    }

    /**
     * Assign subjects to a teacher (replaces existing assignments)
     * POST /api/lesson-plan/teachers/{teacherId}/subjects
     * Body: { subject_ids: [1, 2, 3], branch_id: 1 }
     */
    public function assignSubjects(Request $request, $teacherId)
    {
        $request->validate([
            'subject_ids'   => 'required|array|min:1',
            'subject_ids.*' => 'exists:qb_subjects,id',
        ]);

        $teacher = User::find($teacherId);
        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Teacher not found.'], 404);
        }

        $branchId = $request->branch_id ?? null;

        // Replace all current assignments for this teacher+branch
        LessonPlanSubject::where('user_id', $teacherId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->delete();

        foreach ($request->subject_ids as $subjectId) {
            LessonPlanSubject::create([
                'user_id'    => $teacherId,
                'subject_id' => $subjectId,
                'branch_id'  => $branchId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subjects assigned successfully.',
            'data'    => LessonPlanSubject::where('user_id', $teacherId)
                ->with('subject:id,name')
                ->get(),
        ]);
    }

    /**
     * My lesson plan — for logged-in teacher
     * GET /api/lesson-plan/my
     */
    public function myLessonPlan(Request $request)
    {
        $user = Auth::user();

        $assignedSubjectIds = LessonPlanSubject::where('user_id', $user->id)->pluck('subject_id');

        $subjects = QbSubject::with(['schoolClass:id,name'])
            ->whereIn('id', $assignedSubjectIds)
            ->get()
            ->map(function ($subject) use ($user) {
                $topics = QbTopic::where('subject_id', $subject->id)
                    ->with(['objectives', 'attachments'])
                    ->get()
                    ->map(function ($topic) use ($user, $subject) {
                        $done = LessonPlanDoneTopic::where('topic_id', $topic->id)
                            ->where('teacher_id', $user->id)
                            ->where('subject_id', $subject->id)
                            ->first();
                        $topic->is_done        = (bool) $done;
                        $topic->completed_date = $done ? $done->completed_date : null;
                        return $topic;
                    });

                $subject->topics       = $topics;
                $subject->total_topics = $topics->count();
                $subject->done_topics  = $topics->where('is_done', true)->count();
                return $subject;
            });

        return response()->json(['success' => true, 'data' => $subjects]);
    }

    /**
     * Toggle topic done/undone status for logged-in teacher
     * POST /api/lesson-plan/topic-status
     * Body: { topic_id, subject_id, status: "done" | "undone" }
     */
    public function topicDoneStatus(Request $request)
    {
        $request->validate([
            'topic_id'   => 'required|exists:qb_topics,id',
            'subject_id' => 'required|exists:qb_subjects,id',
            'status'     => 'required|in:done,undone',
        ]);

        $user = Auth::user();

        if ($request->status === 'done') {
            LessonPlanDoneTopic::firstOrCreate(
                [
                    'topic_id'   => $request->topic_id,
                    'teacher_id' => $user->id,
                    'subject_id' => $request->subject_id,
                ],
                [
                    'branch_id'      => $request->branch_id ?? null,
                    'completed_date' => now()->toDateString(),
                ]
            );
            $message = 'Topic marked as done.';
        } else {
            LessonPlanDoneTopic::where('topic_id', $request->topic_id)
                ->where('teacher_id', $user->id)
                ->where('subject_id', $request->subject_id)
                ->delete();
            $message = 'Topic marked as undone.';
        }

        return response()->json(['success' => true, 'message' => $message]);
    }
}