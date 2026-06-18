<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Section;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        return Section::with('schoolClass')
            ->where('branch_id', $request->branch_id)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'name' => 'required',
            'branch_id' => 'required'
        ]);

        $data['added_by'] = auth()->id();

        return Section::create($data);
    }

    public function show($id)
    {
        return Section::with('schoolClass')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'name' => 'required'
        ]);

        $section->update($data);

        return $section;
    }

    public function destroy($id)
    {
        Section::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Section deleted'
        ]);
    }

    public function sectionsByClass($id){
         return Section::with('schoolClass')
            //->where('branch_id', $request->branch_id)
            ->where('school_class_id',$id)
            ->get();
    }
}