<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index($caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);
        $expenses = $case->expenses()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $expenses
        ]);
    }

    public function store(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'item' => 'required|string',
            'value' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $expense = $case->expenses()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Expense added successfully',
            'data' => $expense
        ], 201);
    }

    public function destroy($id)
    {
        $expense = Expense::whereHas('caseFile', function($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $expense->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Expense deleted successfully'
        ]);
    }
}
