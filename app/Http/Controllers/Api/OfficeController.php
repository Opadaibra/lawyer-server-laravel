<?php
// app/Http/Controllers/Api/OfficeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfficeController extends Controller
{
    /**
     * عرض مكتب المستخدم الحالي (نفس office_id)
     * GET /api/offices
     */
    public function index()
    {
        $officeId = Auth::user()->office_id;

        if (!$officeId) {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'المستخدم غير مرتبط بمكتب',
            ]);
        }

        $office = Office::where('id', $officeId)->first();

        return response()->json([
            'status' => 'success',
            'data' => $office ? [$office] : [],
        ]);
    }

    /**
     * عرض مكتب محدد
     * GET /api/offices/{id}
     */
    public function show($id)
    {
        if ((int) $id !== (int) Auth::user()->office_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح بعرض هذا المكتب',
            ], 403);
        }

        $office = Office::find($id);

        if (!$office) {
            return response()->json([
                'status' => 'error',
                'message' => 'Office not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $office
        ]);
    }

    /**
     * إضافة مكتب جديد
     * POST /api/offices
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $office = Office::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Office created successfully',
            'data' => $office
        ], 201);
    }

    /**
     * تحديث مكتب
     * PUT /api/offices/{id}
     */
    public function update(Request $request, $id)
    {
        if ((int) $id !== (int) Auth::user()->office_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح بتعديل هذا المكتب',
            ], 403);
        }

        $office = Office::find($id);

        if (!$office) {
            return response()->json([
                'status' => 'error',
                'message' => 'Office not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $office->update($request->only(['name', 'address', 'phone']));

        return response()->json([
            'status' => 'success',
            'message' => 'Office updated successfully',
            'data' => $office
        ]);
    }

    /**
     * حذف مكتب
     * DELETE /api/offices/{id}
     */
    public function destroy($id)
    {
        if ((int) $id !== (int) Auth::user()->office_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح بحذف هذا المكتب',
            ], 403);
        }

        $office = Office::find($id);

        if (!$office) {
            return response()->json([
                'status' => 'error',
                'message' => 'Office not found'
            ], 404);
        }

        $office->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Office deleted successfully'
        ]);
    }

    /**
     * عرض المستخدمين التابعين لمكتب
     * GET /api/offices/{id}/users
     */
    public function users($id)
    {
        if ((int) $id !== (int) Auth::user()->office_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح',
            ], 403);
        }

        $office = Office::with('users')->find($id);

        if (!$office) {
            return response()->json([
                'status' => 'error',
                'message' => 'Office not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'office' => $office->name,
            'users' => $office->users
        ]);
    }

    /**
     * عرض القضايا التابعة لمكتب
     * GET /api/offices/{id}/cases
     */
    public function cases($id)
    {
        if ((int) $id !== (int) Auth::user()->office_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح',
            ], 403);
        }

        $office = Office::find($id);

        if (!$office) {
            return response()->json([
                'status' => 'error',
                'message' => 'Office not found'
            ], 404);
        }

        $userIds = User::where('office_id', $office->id)->pluck('id');
        $cases = CaseFile::whereIn('user_id', $userIds)->latest()->get();

        return response()->json([
            'status' => 'success',
            'office' => $office->name,
            'cases' => $cases
        ]);
    }
}