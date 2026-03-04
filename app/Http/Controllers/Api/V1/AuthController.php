<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'cnic' => $request->input('cnic'),
            'mobile' => $request->input('mobile'),
            'nationality' => $request->input('nationality'),
            'password' => $request->input('password'),
        ]);

        $user->refresh();

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success(
            data: ['user' => $this->userResponse($user)],
            message: 'Registration successful. Please check your email to verify your account.',
            status: 201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            $email = $user->email;
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->error(
                message: 'Please verify your email address before logging in. Check your inbox for the verification link.',
                errorCode: 'EMAIL_NOT_VERIFIED',
                status: 403,
                data: ['email' => $email],
            );
        }

        if (! $user->is_active) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->error(
                message: 'Your account has been deactivated. Please contact support.',
                errorCode: 'UNAUTHORIZED',
                status: 403,
            );
        }

        return $this->success(
            data: ['user' => $this->userResponse($user)],
            message: 'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success(
            message: 'Logged out successfully.',
        );
    }

    public function user(Request $request): JsonResponse
    {
        return $this->success(
            data: ['user' => $this->userResponse($request->user())],
            message: 'User retrieved successfully.',
        );
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->route('id'));

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->error(
                message: 'Invalid verification link.',
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Email already verified.');
        }

        $user->markEmailAsVerified();

        return $this->success(message: 'Email verified successfully. You can now log in.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->input('email'))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return $this->success(
            message: 'If your email is registered and unverified, we have sent a new verification link.',
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('cnic', $request->input('cnic'))->first();

        if ($user) {
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        return $this->success(
            message: 'If your CNIC is registered, we have sent a password reset link to your email address.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(
                message: 'Your password has been reset successfully. You can now log in.',
            );
        }

        return $this->error(
            message: __($status),
            errorCode: 'VALIDATION_ERROR',
            status: 422,
        );
    }

    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'cnic' => $user->cnic,
            'mobile' => $user->mobile,
            'nationality' => $user->nationality,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
        ];
    }
}
