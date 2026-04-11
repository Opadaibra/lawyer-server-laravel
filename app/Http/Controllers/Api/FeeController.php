<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Fee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FeeController extends Controller
{
    public function index($caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);
        $fees = $case->fees()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $fees
        ]);
    }

    public function store(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'value' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $fee = $case->fees()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Fee record added successfully',
            'data' => $fee
        ], 201);
    }

    public function destroy($id)
    {
        $fee = Fee::whereHas('caseFile', function($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $fee->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Fee record deleted successfully'
        ]);
    }
}
