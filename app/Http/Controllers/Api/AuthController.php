<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // مهم: استدعي Hash
use Illuminate\Support\Facades\Validator;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'sometimes|in:MANAGER,EDITOR,VIEWER,LAWYER'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // إنشاء المستخدم مع تشفير كلمة المرور
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // ✅ صح! لازم Hash
            'role' => $request->role ?? 'LAWYER'
        ]);

        // إذا كان المستخدم محامياً، ننشئ له مكتباً ونربطه به
        if ($user->role === 'LAWYER') {
            $office = \App\Models\Office::create([
                'name' => 'مكتب ' . $user->name,
            ]);

            $user->update(['office_id' => $office->id]);
        }

        $token = Auth::login($user);

        return $this->respondWithTokens($token, $user, 'User registered successfully', 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = Auth::user();

        return $this->respondWithTokens($token, $user, 'Login successful');
    }

    public function logout(Request $request)
    {
        if ($request->has('refresh_token')) {
            RefreshToken::where('token', $request->refresh_token)->delete();
        }

        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string'
        ]);

        $refreshToken = RefreshToken::where('token', $request->refresh_token)->first();

        if (!$refreshToken || $refreshToken->expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        $user = $refreshToken->user;

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 401);
        }

        // Generate new access token
        $newToken = Auth::login($user);

        // Rotate refresh token: destroy the old one and generate a new one
        $refreshToken->delete();

        return $this->respondWithTokens($newToken, $user, 'Token refreshed successfully');
    }

    protected function respondWithTokens($token, $user, $message = 'Success', $status = 200)
    {
        $refreshTokenString = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $refreshTokenString,
            'expires_at' => Carbon::now()->addMinutes(config('jwt.refresh_ttl')),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
                'refresh_token' => $refreshTokenString,
            ]
        ], $status);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|different:current_password',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect'
            ], 401);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Delete all refresh tokens except the current one (if you track it)
        // Or just delete all to be more secure
        RefreshToken::where('user_id', $user->id)->delete();

        // Generate new access token for current session
        $newToken = Auth::login($user);

        // Generate new refresh token
        $refreshTokenString = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $refreshTokenString,
            'expires_at' => Carbon::now()->addMinutes(config('jwt.refresh_ttl')),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
            'authorization' => [
                'token' => $newToken,
                'type' => 'bearer',
                'refresh_token' => $refreshTokenString,
            ]
        ]);
    }
    public function updateProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Delete old picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Store new picture
        $path = $request->file('image')->store('profile_pictures', 'public');

        $user->update([
            'profile_picture' => $path
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile picture updated successfully',
            'profile_picture_url' => $user->profile_picture_url,
            'user' => $user
        ]);
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        
        $now = Carbon::now();

        if ($user->last_otp_requested_at) {
            // Reset daily count if last request was on a different day
            if (!$user->last_otp_requested_at->isToday()) {
                $user->otp_request_count = 0;
            } else {
                // Apply rate limiting based on current request count today
                if ($user->otp_request_count >= 4) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'لقد تجاوزت الحد الأقصى لطلب رمز التحقق اليوم (4 مرات). الرجاء المحاولة غداً.'
                    ], 429);
                }

                $diffInMinutes = $user->last_otp_requested_at->diffInMinutes($now);

                if ($user->otp_request_count == 1 && $diffInMinutes < 2) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'الرجاء الانتظار دقيقتين قبل طلب رمز تحقق جديد.'
                    ], 429);
                }

                if ($user->otp_request_count == 2 && $diffInMinutes < 30) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'الرجاء الانتظار 30 دقيقة قبل طلب رمز تحقق جديد.'
                    ], 429);
                }

                if ($user->otp_request_count == 3 && $diffInMinutes < 60) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'الرجاء الانتظار ساعة قبل طلب رمز تحقق جديد.'
                    ], 429);
                }
            }
        }

        // Generate a 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(15),
            'last_otp_requested_at' => $now,
            'otp_request_count' => $user->otp_request_count + 1
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\SendOtpMail($otp));
            
            return response()->json([
                'status' => 'success',
                'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني بنجاح.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إرسال البريد الإلكتروني. يرجى المحاولة لاحقاً.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->otp !== $request->otp || $user->otp_expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم التحقق من الرمز بنجاح.'
        ]);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Double check OTP
        if ($user->otp !== $request->otp || $user->otp_expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'
            ], 400);
        }

        // Reset password
        $user->update([
            'password' => Hash::make($request->password),
            'otp' => null,
            'otp_expires_at' => null
        ]);

        // Revoke all existing sessions
        RefreshToken::where('user_id', $user->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.'
        ]);
    }
}