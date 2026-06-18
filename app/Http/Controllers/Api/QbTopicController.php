<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbTopic;
use App\Models\QbTopicAttachment;
use App\Models\QbTopicObjective;
use App\Models\LessonPlanSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class QbTopicController extends Controller
{
    /**
     * List topics — filtered by subject/class/branch
     * GET /api/qb-topics?subject_id=1&class_id=1&branch_id=1
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = QbTopic::with([
            'subject:id,name',
            'schoolClass:id,name',
            'attachments',
            'objectives',
        ]);

        // Teachers only see topics for their assigned subjects
        if ($user->user_role === 4) { // Teacher
            $assignedSubjectIds = LessonPlanSubject::where('user_id', $user->id)
                ->pluck('subject_id');
            $query->whereIn('subject_id', $assignedSubjectIds);
        }

        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        $data = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a topic (or multiple topics at once)
     * POST /api/qb-topics
     * Body: { class_id, subject_id, branch_id, topic_names: ["Topic 1", "Topic 2"] }
     * OR single: { class_id, subject_id, branch_id, name }
     */
    public function store(Request $request)
    {
        $request->validate([
            'class_id'   => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:qb_subjects,id',
        ]);

        $created = [];

        // Support bulk creation (array of names) or single creation
        if ($request->has('topic_names') && is_array($request->topic_names)) {
            $request->validate(['topic_names' => 'required|array|min:1', 'topic_names.*' => 'required|string']);
            foreach ($request->topic_names as $name) {
                $created[] = QbTopic::create([
                    'branch_id'  => $request->branch_id,
                    'class_id'   => $request->class_id,
                    'subject_id' => $request->subject_id,
                    'name'       => trim($name),
                ]);
            }
        } else {
            $request->validate(['name' => 'required|string|max:255']);
            $created[] = QbTopic::create([
                'branch_id'  => $request->branch_id,
                'class_id'   => $request->class_id,
                'subject_id' => $request->subject_id,
                'name'       => $request->name,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' topic(s) created successfully.',
            'data'    => $created,
        ], 201);
    }

    /**
     * Show single topic with full lesson plan detail
     * GET /api/qb-topics/{id}
     */
    public function show($id)
    {
        $topic = QbTopic::with([
            'subject:id,name',
            'schoolClass:id,name',
            'attachments',
            'objectives',
            'questions',
        ])->find($id);

        if (!$topic) {
            return response()->json(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $topic]);
    }

    /**
     * Update basic topic info
     * PUT /api/qb-topics/{id}
     */
    public function update(Request $request, $id)
    {
        $topic = QbTopic::find($id);

        if (!$topic) {
            return response()->json(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'subject_id' => 'sometimes|exists:qb_subjects,id',
            'class_id'   => 'sometimes|exists:school_classes,id',
        ]);

        $topic->update($request->only([
            'name', 'class_id', 'subject_id', 'branch_id',
            'description', 'methodology', 'resources', 'duration_minutes',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Topic updated successfully.',
            'data'    => $topic->load(['subject:id,name', 'schoolClass:id,name']),
        ]);
    }

    /**
     * Update Lesson Plan content for a topic (teacher-facing)
     * This replaces attachments & objectives then saves lesson plan fields.
     * PUT /api/qb-topics/{id}/lesson-plan
     */
    public function updateLessonPlan(Request $request, $id)
    {
        $topic = QbTopic::find($id);

        if (!$topic) {
            return response()->json(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        $request->validate([
            'objectives'    => 'nullable|array',
            'objectives.*'  => 'required|string',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,doc,docx|max:5120',
        ]);

        // Update lesson plan fields
        $topic->update($request->only([
            'name', 'description', 'methodology', 'resources', 'duration_minutes',
        ]));

        // Replace objectives
        QbTopicObjective::where('topic_id', $topic->id)->delete();
        if ($request->objectives) {
            foreach ($request->objectives as $obj) {
                QbTopicObjective::create([
                    'topic_id'  => $topic->id,
                    'objective' => $obj,
                ]);
            }
        }

        // Replace attachments (if new files uploaded)
        if ($request->hasFile('attachments')) {
            QbTopicAttachment::where('topic_id', $topic->id)->delete();
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('lesson_plan/attachments', 'public');
                QbTopicAttachment::create([
                    'topic_id'  => $topic->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Lesson plan updated successfully.',
            'data'    => $topic->load(['attachments', 'objectives']),
        ]);
    }

    /**
     * Delete a topic
     * DELETE /api/qb-topics/{id}
     */
    public function destroy($id)
    {
        $topic = QbTopic::find($id);

        if (!$topic) {
            return response()->json(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        $topic->delete(); // cascades to attachments, objectives, done_topics

        return response()->json(['success' => true, 'message' => 'Topic deleted successfully.']);
    }
}