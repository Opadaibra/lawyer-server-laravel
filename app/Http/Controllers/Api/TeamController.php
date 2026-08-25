<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    /**
     * جلب أعضاء الفريق في نفس المكتب (بما فيهم المدير نفسه)
     */
    public function index()
    {
        $user = Auth::user();

        if (!in_array($user->role, ['MANAGER', 'LAWYER'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only managers or lawyers can manage team members.'
            ], 403);
        }

        if (!$user->office_id) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        // ✅ إزالة شرط استثناء المدير نفسه - الآن يظهر الكل
        $team = User::where('office_id', $user->office_id)
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $team
        ]);
    }

    /**
     * إضافة عضو جديد للفريق (محامي أو موظف)
     */
    public function store(Request $request)
    {
        $manager = Auth::user();

        if (!in_array($manager->role, ['MANAGER', 'LAWYER'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only managers or lawyers can add team members.'
            ], 403);
        }

        // إذا لم يكن لديه مكتب، نقوم بإنشاء واحد تلقائياً
        if (!$manager->office_id) {
            $office = Office::create([
                'name' => 'مكتب ' . $manager->name,
            ]);
            
            $manager->office_id = $office->id;
            $manager->save();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:MANAGER,EDITOR,VIEWER,LAWYER' 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'office_id' => $manager->office_id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Team member added successfully',
            'data' => $user
        ], 201);
    }

    /**
     * PUT /api/team/{id}
     * تعديل معلومات عضو الفريق (أي عضو بما فيهم المدير نفسه)
     */
    public function update(Request $request, $id)
    {
        $manager = Auth::user();

        if (!in_array($manager->role, ['MANAGER', 'LAWYER'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only managers or lawyers can update team members.'
            ], 403);
        }

        // البحث عن العضو في نفس المكتب
        $user = User::where('office_id', $manager->office_id)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your team'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|required|in:MANAGER,EDITOR,VIEWER,LAWYER'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // تجميع البيانات المراد تحديثها
        $updateData = [];
        
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        
        if ($request->has('role')) {
            // ✅ إزالة الفاليديشن الخاص بعدم تغيير دور المدير الوحيد
            // المسموح بتغيير أي دور لأي شخص حتى لو كان مديراً وحيداً
            $updateData['role'] = $request->role;
        }

        if (empty($updateData)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No data to update'
            ], 400);
        }

        $user->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Team member updated successfully',
            'data' => $user
        ]);
    }

    /**
     * PUT /api/team/{id}/change-password
     * تغيير كلمة مرور أي عضو في الفريق (بما فيهم المدير نفسه)
     */
    public function changePassword(Request $request, $id)
    {
        $manager = Auth::user();

        if (!in_array($manager->role, ['MANAGER', 'LAWYER'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only managers or lawyers can change team members passwords.'
            ], 403);
        }

        // البحث عن العضو في نفس المكتب
        $user = User::where('office_id', $manager->office_id)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your team'
            ], 404);
        }

        // ✅ إزالة الشرط الذي يمنع تغيير كلمة مرور المدير نفسه
        // الآن مسموح للمدير تغيير كلمة مرور أي شخص حتى نفسه

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    /**
     * DELETE /api/team/{id}
     * حذف موظف من المكتب
     */
    public function destroy($id)
    {
        $manager = Auth::user();

        if (!in_array($manager->role, ['MANAGER', 'LAWYER'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::where('office_id', $manager->office_id)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your team'
            ], 404);
        }

        // ✅ منع حذف نفسه فقط (حماية)
        if ($user->id === $manager->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Team member removed successfully'
        ]);
    }
}