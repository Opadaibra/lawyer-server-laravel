<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\CaseNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CaseNoteController extends Controller
{
    public function index($caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);
        $notes = $case->notes()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $notes
        ]);
    }

    public function store(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $note = $case->notes()->create([
            'date' => $request->date,
            'content' => $request->content
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Note added successfully',
            'data' => $note 
        ], 201);
    }

    public function destroy($id)
    {
        $note = CaseNote::whereHas('caseFile', function($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $note->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Note deleted successfully'
        ]);
    }
}
