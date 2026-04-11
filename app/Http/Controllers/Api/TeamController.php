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
     * جلب أعضاء الفريق في نفس المكتب
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

        $team = User::where('office_id', $user->office_id)
            ->where('id', '!=', $user->id) 
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

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Team member removed successfully'
        ]);
    }
}
