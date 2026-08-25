<?php
// app/Http/Controllers/Api/ClientController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    // GET /clients - جلب كل الموكلين
    public function index()
    {
        $clients = Client::whereIn('user_id', Auth::user()->office_users_ids)
            ->with('cases')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clients
        ]);
    }

    // POST /clients - إنشاء موكل جديد
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'power_of_attorney_number' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // إنشاء حساب الدخول للموكل
        $clientUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'CLIENT',
            'office_id' => Auth::user()->office_id
        ]);

        // إنشاء الملف التعريفي للموكل
        $client = Client::create([
            'user_id' => Auth::id(),
            'client_user_id' => $clientUser->id,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'notes' => $request->notes,
            'power_of_attorney_number' => $request->power_of_attorney_number
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Client account and profile created successfully',
            'data' => $client
        ], 201);
    }


    // GET /client-portal/cases - جلب دعاوى الموكل (لحساب الموكل)
    public function portalCases()
    {
        $user = Auth::user();
        if ($user->role !== 'CLIENT') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $clientRecord = Client::where('client_user_id', $user->id)->first();
        if (!$clientRecord) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $cases = $clientRecord->cases()
            ->with(['tasks', 'minutes', 'files', 'sessions'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cases
        ]);
    }

    // GET /client-portal/fees - جلب أتعاب الموكل
    public function portalFees()
    {
        $user = Auth::user();
        if ($user->role !== 'CLIENT') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $clientRecord = Client::where('client_user_id', $user->id)->first();
        if (!$clientRecord) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_fees' => 0,
                    'total_paid' => 0, // In case total_fees_payments represents paid
                    'fees_records' => []
                ]
            ]);
        }

        $caseIds = $clientRecord->cases()->pluck('id');

        $fees = \App\Models\Fee::whereIn('case_file_id', $caseIds)
            ->with('caseFile:id,case_number,court,total_fees_payments')
            ->orderBy('date', 'desc')
            ->get();

        $totalFeesAmount = $fees->sum('value');
        $totalPaidAmount = $clientRecord->cases()->sum('total_fees_payments');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_fees' => $totalFeesAmount,
                'total_paid' => $totalPaidAmount,
                'fees_records' => $fees
            ]
        ]);
    }
    // GET /clients/{id} - جلب موكل واحد
    public function show($id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)
            ->with([
                'cases' => function ($query) {
                    $query->with(['tasks', 'minutes', 'files']);
                }
            ])
            ->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $client
        ]);
    }

    // PUT/PATCH /clients/{id} - تحديث موكل
    public function update(Request $request, $id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'power_of_attorney_number' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update($request->only([
            'name',
            'phone',
            'email',
            'address',
            'notes',
            'power_of_attorney_number'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Client updated successfully',
            'data' => $client
        ]);
    }

    // DELETE /clients/{id} - حذف موكل
    public function destroy($id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        // تحقق إذا كان للموكل دعاوى
        if ($client->cases()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete client with existing cases'
            ], 409);
        }

        $client->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Client deleted successfully'
        ]);
    }

    // GET /clients/search/{query} - بحث عن موكلين
    public function search($query)
    {
        $clients = Client::whereIn('user_id', Auth::user()->office_users_ids)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->with('cases')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clients
        ]);
    }

    // GET /clients/{id}/cases - جلب دعاوى موكل معين
    public function cases($id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        $cases = $client->cases()
            ->with(['tasks', 'minutes', 'files'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cases
        ]);
    }

    // POST /api/clients/{id}/upload-profile-picture
    public function uploadProfilePicture(Request $request, $id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Delete old picture if exists
        if ($client->profile_picture) {
            Storage::disk('public')->delete($client->profile_picture);
        }

        // Store new picture
        $path = $request->file('image')->store('client_profiles', 'public');

        $client->update([
            'profile_picture' => $path
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Client profile picture updated successfully',
            'profile_picture_url' => $client->profile_picture_url,
            'data' => $client
        ]);
    }

    /**
     * PUT /api/clients/{id}/change-password
     * تغيير كلمة مرور الموكل (بدون OTP)
     */
    public function changePassword(Request $request, $id)
    {
        // التحقق من وجود الموكل وصلاحيات المكتب
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)->find($id);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }

        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // البحث عن حساب المستخدم المرتبط بالموكل
        $user = User::find($client->client_user_id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User account not found for this client'
            ], 404);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Client password changed successfully',
            'data' => [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'email' => $user->email
            ]
        ]);
    }
}