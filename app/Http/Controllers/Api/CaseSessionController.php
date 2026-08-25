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
    public function index(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);
        $query = $case->sessions()->latest();
        
        if ($request->has('archived') && $request->archived) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $sessions = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $sessions
        ]);
    }

    public function store(Request $request, $caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)->findOrFail($caseId);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d H:i:s|after_or_equal:today',
            'decisions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // إتاحة جلسة واحدة فعالة فقط للقضية
        $case->sessions()->whereNull('archived_at')->update(['archived_at' => now()]);

        // تحويل الوقت لتايم زون التطبيق
        $data = $request->all();
        $data['date'] = \Carbon\Carbon::parse($request->date)->format('Y-m-d H:i:s');

        $session = $case->sessions()->create($data);

        // إضافة إشعار في قاعدة البيانات لكل أعضاء المكتب
        foreach (Auth::user()->office_users_ids as $userId) {
            \App\Models\AppNotification::create([
                'user_id' => $userId,
                'title' => 'جلسة جديدة',
                'message' => "تمت إضافة جلسة جديدة للقضية رقم: {$case->case_number}",
                'case_file_id' => $case->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Session added successfully',
            'data' => $session
        ], 201);
    }

    public function postpone(Request $request, $id)
    {
        $session = CaseSession::whereHas('caseFile', function ($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'new_date' => 'required|date_format:Y-m-d H:i:s|after:today',
            'decisions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 1. أرشفة الجلسة الحالية
        $session->update([
            'archived_at' => now(),
            'decisions' => $request->decisions ?? $session->decisions
        ]);

        // 2. إنشاء جلسة جديدة بالتاريخ الجديد
        $newSession = CaseSession::create([
            'case_file_id' => $session->case_file_id,
            'date' => $request->new_date,
            'notes' => $session->notes
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session postponed successfully',
            'data' => $newSession
        ]);
    }

    public function update(Request $request, $id)
    {
        $session = CaseSession::whereHas('caseFile', function ($q) {
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
        $session = CaseSession::whereHas('caseFile', function ($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session deleted successfully'
        ]);
    }

    public function archive($id)
    {
        $session = CaseSession::whereHas('caseFile', function ($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        if ($session->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is already archived'
            ], 400);
        }

        $session->update(['archived_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session archived successfully',
            'data' => $session
        ]);
    }

    public function unarchive($id)
    {
        $session = CaseSession::whereHas('caseFile', function ($q) {
            $q->whereIn('user_id', Auth::user()->office_users_ids);
        })->findOrFail($id);

        if (!$session->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not archived'
            ], 400);
        }

        $session->update(['archived_at' => null]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session unarchived successfully',
            'data' => $session
        ]);
    }
}
