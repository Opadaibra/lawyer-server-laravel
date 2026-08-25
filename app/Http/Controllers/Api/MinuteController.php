<?php
// app/Http/Controllers/Api/MinuteController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Minute;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MinuteController extends Controller
{
    /**
     * GET /api/minutes
     * جلب كل المحاضر للمستخدم الحالي
     */
    /**
     * GET /api/minutes
     * جلب كل المحاضر للمستخدم الحالي
     */
    public function index(Request $request)
    {
        try {
            // ✅ صح: Minute::where بدل Task::
            $query = Minute::whereIn('user_id', Auth::user()->office_users_ids)
                ->with(['case', 'files']);

            // فلترة حسب القضية
            if ($request->has('case_id')) {
                $query->where('case_file_id', $request->case_id);
            }

            // المحاضر المؤرشفة أو غير المؤرشفة
            if ($request->has('archived')) {
                if ($request->archived) {
                    $query->whereNotNull('archived_at');
                } else {
                    $query->whereNull('archived_at');
                }
            }

            $minutes = $query->latest()->get();

            return response()->json([
                'status' => 'success',
                'count' => $minutes->count(),
                'data' => $minutes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load minutes: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * POST /api/minutes
     * إنشاء محضر جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_file_id' => 'nullable|exists:case_files,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'date' => 'nullable|date',
            'number' => 'nullable|string',
            'court_department' => 'nullable|string',
            'client_status' => 'nullable|string',
            'opponent' => 'nullable|string',
            'opponent_status' => 'nullable|string',
            'last_procedure' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من أن القضية تخص المستخدم
        if ($request->filled('case_file_id')) {
            $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)
                ->where('id', $request->case_file_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or does not belong to you'
                ], 403);
            }
        }

        // استخدم only أو array مباشرة
        $minute = Minute::create([
            'user_id' => Auth::id(),
            'case_file_id' => $request->case_file_id,
            'title' => $request->title,
            'content' => $request->content,
            'date' => $request->date,
            'number' => $request->number,
            'court_department' => $request->court_department,
            'client_status' => $request->client_status,
            'opponent' => $request->opponent,
            'opponent_status' => $request->opponent_status,
            'last_procedure' => $request->last_procedure,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Minute created successfully',
            'data' => $minute->load(['case', 'files'])
        ], 201);
    }

    /**
     * GET /api/minutes/{id}
     * جلب محضر محدد
     */
    public function show($id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)
            ->with(['case', 'files'])
            ->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $minute
        ]);
    }

    /**
     * PUT/PATCH /api/minutes/{id}
     * تحديث محضر
     */
    public function update(Request $request, $id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'date' => 'nullable|date',
            'number' => 'nullable|string',
            'court_department' => 'nullable|string',
            'client_status' => 'nullable|string',
            'opponent' => 'nullable|string',
            'opponent_status' => 'nullable|string',
            'last_procedure' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $minute->update($request->only([
            'title',
            'content',
            'date',
            'number',
            'court_department',
            'client_status',
            'opponent',
            'opponent_status',
            'last_procedure'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Minute updated successfully',
            'data' => $minute->fresh(['case', 'files'])
        ]);
    }

    /**
     * DELETE /api/minutes/{id}
     * حذف محضر
     */
    public function destroy($id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        // حذف العلاقات مع الملفات أولاً
        $minute->files()->detach();
        $minute->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Minute deleted successfully'
        ]);
    }

    /**
     * POST /api/minutes/{id}/archive
     * أرشفة محضر
     */
    public function archive($id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        if ($minute->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute is already archived'
            ], 400);
        }

        $minute->update(['archived_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Minute archived successfully',
            'data' => $minute
        ]);
    }

    /**
     * POST /api/minutes/{id}/unarchive
     * إلغاء أرشفة محضر
     */
    public function unarchive($id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        if (!$minute->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute is not archived'
            ], 400);
        }

        $minute->update(['archived_at' => null]);

        return response()->json([
            'status' => 'success',
            'message' => 'Minute unarchived successfully',
            'data' => $minute
        ]);
    }

    /**
     * POST /api/minutes/{id}/attach-files
     * إضافة ملفات للمحضر
     */
    public function attachFiles(Request $request, $id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:files,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من أن الملفات تخص المستخدم
        $files = \App\Models\File::whereIn('id', $request->file_ids)
            ->whereIn('user_id', Auth::user()->office_users_ids)
            ->pluck('id')
            ->toArray();

        if (count($files) !== count($request->file_ids)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more files do not belong to you'
            ], 403);
        }

        $minute->files()->syncWithoutDetaching($request->file_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Files attached successfully',
            'data' => $minute->load('files')
        ]);
    }

    /**
     * POST /api/minutes/{id}/detach-file
     * إزالة ملف من المحضر
     */
    public function detachFile(Request $request, $id)
    {
        $minute = Minute::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$minute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minute not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file_id' => 'required|exists:files,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $minute->files()->detach($request->file_id);

        return response()->json([
            'status' => 'success',
            'message' => 'File detached successfully'
        ]);
    }

    /**
     * GET /api/minutes/case/{caseId}
     * جلب كل محاضر قضية معينة
     */
    public function getCaseMinutes($caseId)
    {
        $case = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)
            ->where('id', $caseId)
            ->firstOrFail();

        $minutes = Minute::whereIn('user_id', Auth::user()->office_users_ids)
            ->where('case_file_id', $caseId)
            ->with(['files'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'case_id' => (int) $caseId,
            'case_number' => $case->case_number,
            'count' => $minutes->count(),
            'data' => $minutes
        ]);
    }


    /**
     * GET /api/minutes/client/{clientId}/grouped
     * جلب محاضر الموكل مجمعة حسب كل قضية
     */
    public function getClientMinutesGrouped($clientId)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'الموكل غير موجود أو لا يتبع مكتبك',
            ], 404);
        }

        $cases = CaseFile::whereIn('user_id', Auth::user()->office_users_ids)
            ->where('client_id', $clientId)
            ->with([
                'minutes' => function ($query) {
                    $query->whereIn('user_id', Auth::user()->office_users_ids)
                        ->with('files')
                        ->latest();
                },
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'client_id' => (int) $clientId,
            'client_name' => $client->name,
            'total_cases' => $cases->count(),
            'data' => $cases->map(function ($case) {
                return [
                    'case_id' => $case->id,
                    'case_number' => $case->case_number,
                    'case_type' => $case->case_type,
                    'court' => $case->court,
                    'minutes_count' => $case->minutes->count(),
                    'minutes' => $case->minutes,
                ];
            }),
        ]);
    }
}