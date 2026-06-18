<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeHead;
use App\Models\Section;
use Illuminate\Support\Facades\Auth;

class FeeHeadController extends Controller
{
    // GET ALL
    public function index(Request $request)
    {
        $data = FeeHead::where('branch_id', 1)
            ->where('section_id', $request->section_id)
            ->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // STORE
    public function store(Request $request)
    {
        $request->validate([
            'section_id' => 'required|exists:sections,id',
            'heads' => 'required|array|min:1',
            'heads.*.head_name' => 'required|string|max:255',
            'heads.*.head_amount' => 'required|numeric',
            'heads.*.head_frequency' => 'nullable|string',
        ]);

        $section = Section::findOrFail($request->section_id);

        // Get all sections of same class
        $sections = Section::where('school_class_id', $section->school_class_id)
            ->where('branch_id', 1)
            ->get();

        // Check if ANY fee head already exists for this class
        $existing = FeeHead::whereIn('section_id', $sections->pluck('id'))->exists();

        $targetSections = [];

        if (!$existing) {
            // FIRST TIME → apply to all sections
            $targetSections = $sections;
        } else {
            // UPDATE → only selected section
            $targetSections = collect([$section]);
        }

        // 🔴 IMPORTANT: Delete old data (this makes it UPDATE-safe)
        FeeHead::whereIn('section_id', $targetSections->pluck('id'))
            ->where('branch_id', 1)
            ->delete();

        // 🟢 Insert new data
        $insertData = [];

        foreach ($targetSections as $sec) {
            foreach ($request->heads as $head) {
                $insertData[] = [
                    'added_by' => Auth::id(),
                    'branch_id' => 1,
                    'section_id' => $sec->id,
                    'head_name' => $head['head_name'],
                    'head_amount' => $head['head_amount'],
                    'head_frequency' => $head['head_frequency'] ?? 'Monthly',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        FeeHead::insert($insertData);

        return response()->json([
            'status' => true,
            'message' => !$existing
                ? 'Fee Heads created for all sections (first time setup)'
                : 'Fee Heads updated successfully'
        ]);
    }

    // SHOW SINGLE
    public function show($id)
    {
        $data = FeeHead::where('branch_id', 1)->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $data = FeeHead::where('branch_id', 1)->findOrFail($id);

        $request->validate([
            'head_name' => 'sometimes|required|string|max:255',
            'head_amount' => 'sometimes|required|numeric',
            'head_frequency' => 'nullable|string'
        ]);

        $data->update([
            'section_id' => $request->section_id ?? $data->section_id,
            'head_name' => $request->head_name ?? $data->head_name,
            'head_amount' => $request->head_amount ?? $data->head_amount,
            'head_frequency' => $request->head_frequency ?? $data->head_frequency,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Fee Head updated successfully',
            'data' => $data
        ]);
    }

    // DELETE
    public function destroy($id)
    {
        $data = FeeHead::where('branch_id', 1)->findOrFail($id);
        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Fee Head deleted successfully'
        ]);
    }

    public function feeHeadsBySection($section_id){
        
        $data = FeeHead::where('section_id',$section_id)->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}