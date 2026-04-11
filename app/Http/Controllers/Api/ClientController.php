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

    // GET /clients/{id} - جلب موكل واحد
    public function show($id)
    {
        $client = Client::whereIn('user_id', Auth::user()->office_users_ids)
                        ->with(['cases' => function($query) {
                            $query->with(['tasks', 'minutes', 'files']);
                        }])
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
            'name', 'phone', 'email', 'address', 'notes', 'power_of_attorney_number'
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
                        ->where(function($q) use ($query) {
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
}