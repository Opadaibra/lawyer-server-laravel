<?php
// app/Http/Controllers/Api/FileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\File;
use App\Models\CaseFile;
use App\Models\Task;
use App\Models\Minute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Pail\ValueObjects\Origin\Console;
class FileController extends Controller
{
    public function upload(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // max 10MB
            'case_id' => 'nullable|exists:case_files,id',
            'task_id' => 'nullable|exists:tasks,id',
            'minute_id' => 'nullable|exists:minutes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $uploadedFile = $request->file('file');
            $fileName = time() . '_' . $uploadedFile->getClientOriginalName();
            $filePath = $uploadedFile->storeAs('uploads', $fileName, 'public');

            $file = File::create([
                'user_id' => auth()->id(),
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $uploadedFile->getMimeType(),
                'file_size' => $uploadedFile->getSize(),
            ]);

            if ($request->has('case_id')) {
                $file->cases()->attach($request->case_id);
            }

            if ($request->has('task_id')) {
                $file->tasks()->attach($request->task_id);
            }

            if ($request->has('minute_id')) {
                $file->minutes()->attach($request->minute_id);
            }

            return response()->json([
                'message' => 'File uploaded successfully',
                'file' => $file,
                'url' => $file->url
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }


    public function attach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|exists:files,id',
            'case_id' => 'nullable|exists:case_files,id',
            'task_id' => 'nullable|exists:tasks,id',
            'minute_id' => 'nullable|exists:minutes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = File::findOrFail($request->file_id);

        if ($request->has('case_id')) {
            $file->cases()->syncWithoutDetaching([$request->case_id]);
        }

        if ($request->has('task_id')) {
            $file->tasks()->syncWithoutDetaching([$request->task_id]);
        }

        if ($request->has('minute_id')) {
            $file->minutes()->syncWithoutDetaching([$request->minute_id]);
        }

        return response()->json([
            'message' => 'File attached successfully',
            'file' => $file->load(['cases', 'tasks', 'minutes'])
        ]);
    }


    public function detach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|exists:files,id',
            'case_id' => 'nullable|exists:case_files,id',
            'task_id' => 'nullable|exists:tasks,id',
            'minute_id' => 'nullable|exists:minutes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = File::findOrFail($request->file_id);

        if ($request->has('case_id')) {
            $file->cases()->detach($request->case_id);
        }

        if ($request->has('task_id')) {
            $file->tasks()->detach($request->task_id);
        }

        if ($request->has('minute_id')) {
            $file->minutes()->detach($request->minute_id);
        }

        return response()->json(['message' => 'File detached successfully']);
    }

    /**
     * عرض جميع ملفات مستخدم معين
     */
    public function index()
    {
        $files = File::whereIn('user_id', auth()->user()->office_users_ids)
            ->with(['cases', 'tasks', 'minutes'])
            ->latest()
            ->get();

        return response()->json($files);
    }

    /**
     * عرض ملف معين
     */
    public function show($id)
    {
        $file = File::whereIn('user_id', auth()->user()->office_users_ids)
            ->with(['cases', 'tasks', 'minutes'])
            ->findOrFail($id);

        return response()->json($file);
    }

    /**
     * حذف ملف
     */
    public function destroy($id)
    {
        $file = File::whereIn('user_id', auth()->user()->office_users_ids)->findOrFail($id);

        // حذف الملف الفعلي من التخزين
        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        // حذف العلاقات ثم الملف
        $file->cases()->detach();
        $file->tasks()->detach();
        $file->minutes()->detach();
        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * تحميل ملف
     */
    public function download($id)
    {
        $file = File::whereIn('user_id', auth()->user()->office_users_ids)->findOrFail($id);

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($file->file_path, $file->file_name);
    }

    /**
     * رفع ملف وربطه مع كيانات متعددة في طلب واحد
     * 
     * POST /api/files/upload-and-attach
     * 
     * @bodyParam file file required الملف المراد رفعه
     * @bodyParam client_id int optional ربط مع عميل
     * @bodyParam case_id int optional ربط مع قضية
     * @bodyParam task_id int optional ربط مع مهمة
     * @bodyParam minute_id int optional ربط مع محضر
     */
    public function uploadAndAttach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // max 10MB
            'client_id' => 'nullable|exists:clients,id',
            'case_id' => 'nullable|exists:case_files,id',
            'task_id' => 'nullable|exists:tasks,id',
            'minute_id' => 'nullable|exists:minutes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // رفع الملف
            $uploadedFile = $request->file('file');
            $fileName = time() . '_' . $uploadedFile->getClientOriginalName();
            $filePath = $uploadedFile->storeAs('uploads', $fileName, 'public');

            // حفظ الملف في قاعدة البيانات
            $file = File::create([
                'user_id' => auth()->id(),
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $uploadedFile->getMimeType(),
                'file_size' => $uploadedFile->getSize(),
            ]);

            $attachedTo = [];

            // ربط مع عميل
            if ($request->has('client_id')) {
                // هل تريد ربط الملف مع العميل مباشرة؟
                // هذا يتطلب وجود جدول client_file
                // ما في جدول ربط، ممكن نربط مع قضايا العميل
                $client = Client::find($request->client_id);
                if ($client) {
                    // ربط الملف مع كل قضايا العميل
                    $caseIds = $client->cases()->pluck('id')->toArray();
                    if (!empty($caseIds)) {
                        $file->cases()->syncWithoutDetaching($caseIds);
                        $attachedTo['cases'] = $caseIds;
                    }
                }
            }

            // ربط مع قضية
            if ($request->has('case_id')) {
                $file->cases()->syncWithoutDetaching([$request->case_id]);
                $attachedTo['cases'][] = $request->case_id;
            }

            // ربط مع مهمة
            if ($request->has('task_id')) {
                $file->tasks()->syncWithoutDetaching([$request->task_id]);
                $attachedTo['tasks'][] = $request->task_id;
            }

            // ربط مع محضر
            if ($request->has('minute_id')) {
                $file->minutes()->syncWithoutDetaching([$request->minute_id]);
                $attachedTo['minutes'][] = $request->minute_id;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded and attached successfully',
                'file' => $file,
                'url' => $file->url,
                'attached_to' => $attachedTo
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب جميع الملفات المرتبطة بقضية معينة
     * 
     * GET /api/files/by-case/{caseId}
     */
    public function getFilesByCase($caseFileId)
    {
        $files = File::whereHas('cases', function ($query) use ($caseFileId) {
            $query->where('case_file_id', $caseFileId);
        })->get();

        return response()->json($files);
    }

    /**
     * جلب جميع الملفات المرتبطة بمحضر معين
     * 
     * GET /api/files/by-minute/{minuteId}
     */
    public function getFilesByMinute($minuteId)
    {
        $files = File::whereHas('minutes', function ($query) use ($minuteId) {
            $query->where('minutes.id', $minuteId);
        })->get();

        return response()->json($files);
    }

    /**
     * جلب جميع الملفات المرتبطة بمهمة معينة
     * 
     * GET /api/files/by-task/{taskId}
     */
    public function getFilesByTask($taskId)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $task = Task::where('id', $taskId)->first();

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        $files = File::whereHas('tasks', function ($query) use ($taskId) {
            $query->where('tasks.id', $taskId);
        })
            ->with(['cases', 'tasks', 'minutes'])
            ->latest()
            ->get();

        return response()->json([
            'task_id' => $taskId,
            'files' => $files
        ]);
    }
}