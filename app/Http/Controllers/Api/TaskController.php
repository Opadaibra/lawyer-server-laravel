<?php
// app/Http/Controllers/Api/TaskController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\CaseFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{


    /**
     * GET /api/tasks
     * جلب كل المهام للمستخدم الحالي
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = Task::whereIn('user_id', Auth::user()->office_users_ids)
            ->with(['case', 'files']);

        // فلترة حسب الحالة
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب القضية
        if ($request->has('case_id')) {
            $query->where('case_file_id', $request->case_id);
        }

        // المهام المؤرشفة أو غير المؤرشفة
        if ($request->has('archived')) {
            if ($request->archived) {
                $query->whereNotNull('archived_at');
            } else {
                $query->whereNull('archived_at');
            }
        }

        $tasks = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'count' => $tasks->count(),
            'data' => $tasks
        ]);
    }

    /**
     * POST /api/tasks
     * إنشاء مهمة جديدة
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_file_id' => 'nullable|exists:case_files,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'task_type' => 'nullable|string',
            'next_session_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من أن القضية تخص المستخدم إذا تم تحديدها
        if ($request->has('case_file_id')) {
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

        $task = Task::create([
            'user_id' => Auth::id(),
            'case_file_id' => $request->case_file_id,
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'task_type' => $request->task_type,
            'next_session_date' => $request->next_session_date,
            'notes' => $request->notes,
            'status' => $request->status ?? 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $task->load(['case', 'files'])
        ], 201);
    }

    /**
     * GET /api/tasks/{id}
     * جلب مهمة محددة
     */
    public function show($id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)
            ->with(['case', 'files'])
            ->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $task
        ]);
    }

    /**
     * PUT/PATCH /api/tasks/{id}
     * تحديث مهمة
     */
    public function update(Request $request, $id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'case_file_id' => 'nullable|exists:case_files,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'task_type' => 'nullable|string',
            'next_session_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من القضية إذا تم تغييرها
        if ($request->has('case_file_id') && $request->case_file_id != $task->case_file_id) {
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

        $task->update($request->only([
            'case_file_id',
            'title',
            'description',
            'due_date',
            'due_date',
            'status',
            'task_type',
            'next_session_date',
            'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Task updated successfully',
            'data' => $task->fresh(['case', 'files'])
        ]);
    }

    /**
     * DELETE /api/tasks/{id}
     * حذف مهمة
     */
    public function destroy($id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        // حذف العلاقات مع الملفات أولاً
        $task->files()->detach();
        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Task deleted successfully'
        ]);
    }

    /**
     * POST /api/tasks/{id}/archive
     * أرشفة مهمة
     */
    public function archive($id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        if ($task->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task is already archived'
            ], 400);
        }

        $task->update(['archived_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task archived successfully',
            'data' => $task
        ]);
    }

    /**
     * POST /api/tasks/{id}/unarchive
     * إلغاء أرشفة مهمة
     */
    public function unarchive($id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        if (!$task->archived_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task is not archived'
            ], 400);
        }

        $task->update(['archived_at' => null]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task unarchived successfully',
            'data' => $task
        ]);
    }

    /**
     * POST /api/tasks/{id}/attach-files
     * إضافة ملفات للمهمة
     */
    public function attachFiles(Request $request, $id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
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

        $task->files()->syncWithoutDetaching($request->file_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Files attached successfully',
            'data' => $task->load('files')
        ]);
    }

    /**
     * POST /api/tasks/{id}/detach-file
     * إزالة ملف من المهمة
     */
    public function detachFile(Request $request, $id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
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

        $task->files()->detach($request->file_id);

        return response()->json([
            'status' => 'success',
            'message' => 'File detached successfully'
        ]);
    }

    /**
     * GET /api/tasks/upcoming
     * جلب المهام القادمة (التي تنتهي قريباً)
     */
    public function upcoming()
    {
        $tasks = Task::whereIn('user_id', Auth::user()->office_users_ids)
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['case'])
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $tasks->count(),
            'data' => $tasks
        ]);
    }

    /**
     * GET /api/tasks/overdue
     * جلب المهام المتأخرة
     */
    public function overdue()
    {
        $tasks = Task::whereIn('user_id', Auth::user()->office_users_ids)
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['case'])
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $tasks->count(),
            'data' => $tasks
        ]);
    }

    /**
     * POST /api/tasks/{id}/complete
     * تغيير حالة المهمة إلى مكتملة
     */
    public function complete($id)
    {
        $task = Task::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task not found'
            ], 404);
        }

        $task->update(['status' => 'completed']);

        return response()->json([
            'status' => 'success',
            'message' => 'Task marked as completed',
            'data' => $task
        ]);
    }
}