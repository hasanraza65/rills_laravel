<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;

class SchoolClassController extends Controller
{
    public function index(Request $request)
    {
        return SchoolClass::with('sections')->where('branch_id', $request->branch_id)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'branch_id' => 'required'
        ]);

        $data['added_by'] = auth()->id();

        return SchoolClass::create($data);
    }

    public function show($id)
    {
        return SchoolClass::with('sections')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $class = SchoolClass::findOrFail($id);

        $data = $request->validate([
            'name' => 'required'
        ]);

        $class->update($data);

        return $class;
    }

    public function destroy($id)
    {
        SchoolClass::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Class deleted'
        ]);
    }
}