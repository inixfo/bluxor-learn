<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Services\GuestPurchaseClaimService;

class AuthController extends Controller
{
    public function me(Request $request, GuestPurchaseClaimService $claims)
    {
        if ($request->user()?->hasVerifiedEmail()) {
            $claims->claimForVerifiedUser($request->user());
        }

        return response()->json([
            'data' => $request->user()?->fresh()?->load('roles'),
        ]);
    }

    public function register(Request $request)
    {
        if ($request->user()) {
            return response()->json(['message' => 'Already authenticated.'], 409);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::create($data);
        $user->roles()->syncWithoutDetaching(Role::firstOrCreate(['name' => 'customer'])->id);
        event(new Registered($user));
        Auth::login($user);

        return response()->json(['data' => $user->load('roles')], 201);
    }

    public function login(Request $request, GuestPurchaseClaimService $claims)
    {
        if ($request->user()) {
            return response()->json(['message' => 'Already authenticated.'], 409);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            return response()->json(['message' => 'These credentials do not match our records.'], 422);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        if ($request->user()->hasVerifiedEmail()) {
            $claims->claimForVerifiedUser($request->user());
        }

        return response()->json(['data' => $request->user()->fresh()->load('roles')]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['data' => ['message' => 'If that email can receive resets, a reset link has been sent.']]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['data' => ['message' => 'Password has been reset.']]);
    }

    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['data' => ['message' => 'Email is already verified.']]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['data' => ['message' => 'Verification email sent.']]);
    }

    public function verifyEmail(Request $request, int $id, string $hash, GuestPurchaseClaimService $claims)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $claimResult = $claims->claimForVerifiedUser($user);

        return response()->json(['data' => ['verified' => true] + $claimResult]);
    }
}
