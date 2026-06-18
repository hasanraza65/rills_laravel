<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QbQuestionController extends Controller
{
    /**
     * List questions with filters
     * GET /api/qb-questions?class_id=1&subject_id=1&topic_id=1&type=MCQ&branch_id=1&per_page=15
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = QbQuestion::with([
            'schoolClass:id,name',
            'subject:id,name',
            'topic:id,name',
        ]);

        if ($user->user_role !== 1) {
            $branchId = $request->branch_id ?? null;
            if ($branchId) $query->where('branch_id', $branchId);
        } elseif ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->class_id)   $query->where('class_id', $request->class_id);
        if ($request->subject_id) $query->where('subject_id', $request->subject_id);
        if ($request->topic_id)   $query->where('topic_id', $request->topic_id);
        if ($request->type)       $query->where('type', $request->type);

        $perPage = $request->per_page ?? 15;
        $data    = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a question
     * POST /api/qb-questions
     */
    public function store(Request $request)
    {
        $request->validate([
            'class_id'   => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:qb_subjects,id',
            'type'       => 'required|in:MCQ,BLANKS,TRUE_FALSE,SHORT,LONG,PIC,MATCH_COLUMN',
            'question'   => 'required|string',
        ]);

        $payload = $this->buildQuestionPayload($request);

        $question = QbQuestion::create($payload + [
            'branch_id' => $request->branch_id ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully.',
            'data'    => $question->load(['schoolClass:id,name', 'subject:id,name', 'topic:id,name']),
        ], 201);
    }

    /**
     * Show single question
     * GET /api/qb-questions/{id}
     */
    public function show($id)
    {
        $question = QbQuestion::with([
            'schoolClass:id,name',
            'subject:id,name',
            'topic:id,name',
        ])->find($id);

        if (!$question) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $question]);
    }

    /**
     * Update a question
     * PUT /api/qb-questions/{id}
     */
    public function update(Request $request, $id)
    {
        $question = QbQuestion::find($id);

        if (!$question) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        $request->validate([
            'type'     => 'sometimes|in:MCQ,BLANKS,TRUE_FALSE,SHORT,LONG,PIC,MATCH_COLUMN',
            'question' => 'sometimes|string',
        ]);

        $payload = $this->buildQuestionPayload($request, $question);
        $question->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully.',
            'data'    => $question->load(['schoolClass:id,name', 'subject:id,name', 'topic:id,name']),
        ]);
    }

    /**
     * Delete a question
     * DELETE /api/qb-questions/{id}
     */
    public function destroy($id)
    {
        $question = QbQuestion::find($id);

        if (!$question) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        // Delete image if exists
        if ($question->pic1 && file_exists(public_path($question->pic1))) {
            unlink(public_path($question->pic1));
        }

        $question->delete();

        return response()->json(['success' => true, 'message' => 'Question deleted successfully.']);
    }

    // ─── Private Helper ────────────────────────────────────────────

    /**
     * Normalise the request payload based on question type.
     * Works for both store and update.
     */
    private function buildQuestionPayload(Request $request, ?QbQuestion $existing = null): array
    {
        $type = $request->type ?? ($existing->type ?? null);

        $payload = [
            'class_id'   => $request->class_id   ?? $existing?->class_id,
            'subject_id' => $request->subject_id ?? $existing?->subject_id,
            'topic_id'   => $request->topic_id   ?? $existing?->topic_id,
            'type'       => $type,
            'question'   => $request->question   ?? $existing?->question,
            'marks'      => $request->marks       ?? $existing?->marks ?? 1,
        ];

        switch ($type) {
            case 'MCQ':
                $payload['opt1'] = $request->opt1;
                $payload['opt2'] = $request->opt2;
                $payload['opt3'] = $request->opt3;
                $payload['opt4'] = $request->opt4;
                $payload['ans']  = $request->ans;
                break;

            case 'BLANKS':
                $payload['before_blank'] = $request->before_blank;
                $payload['after_blank']  = $request->after_blank;
                $payload['ans']          = $request->ans;
                break;

            case 'TRUE_FALSE':
                // ans should be "True" or "False"
                $payload['ans'] = $request->ans;
                break;

            case 'SHORT':
            case 'LONG':
                $payload['ans'] = $request->ans;
                break;

            case 'PIC':
                $payload['before_blank'] = $request->before_blank;
                $payload['after_blank']  = $request->after_blank;
                $payload['ans']          = $request->ans;
                if ($request->hasFile('pic1')) {
                    $request->validate(['pic1' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);
                    $imageName = time() . '.' . $request->pic1->extension();
                    $request->pic1->move(public_path('uploads/qb_questions'), $imageName);
                    $payload['pic1'] = 'uploads/qb_questions/' . $imageName;
                } elseif ($existing) {
                    $payload['pic1'] = $existing->pic1; // keep existing
                }
                break;

            case 'MATCH_COLUMN':
                // Accept as array from React, or as newline-separated string
                $colA = $request->column_a;
                $colB = $request->column_b;
                if (is_string($colA)) $colA = array_filter(explode("\n", $colA));
                if (is_string($colB)) $colB = array_filter(explode("\n", $colB));
                $payload['column_a'] = array_values($colA);
                $payload['column_b'] = array_values($colB);
                break;
        }

        return $payload;
    }
}