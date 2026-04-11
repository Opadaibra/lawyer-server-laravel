<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\CaseSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CaseSessionController extends Controller
{
    public function index($caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);
        $sessions = $case->sessions()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $sessions
        ]);
    }

    public function store(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'decisions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $session = $case->sessions()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Session added successfully',
            'data' => $session
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $session = CaseSession::whereHas('caseFile', function($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $session->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Session updated successfully',
            'data' => $session
        ]);
    }

    public function destroy($id)
    {
        $session = CaseSession::whereHas('caseFile', function($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session deleted successfully'
        ]);
    }
}
