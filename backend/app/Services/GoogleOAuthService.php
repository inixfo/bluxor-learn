<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleOAuthService
{
    public function __construct(private readonly GuestPurchaseClaimService $claims) {}

    public function redirectUrl(Request $request): string
    {
        $state = Str::random(40);
        $returnTo = $this->safeReturnTo((string) $request->query('return_to', '/account'));
        $request->session()->put('google_oauth', ['state' => $state, 'return_to' => $returnTo]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    public function callback(Request $request): array
    {
        $session = $request->session()->pull('google_oauth');
        if (! is_array($session) || ! hash_equals((string) ($session['state'] ?? ''), (string) $request->query('state'))) {
            throw ValidationException::withMessages(['state' => ['Invalid Google sign-in state.']]);
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            throw ValidationException::withMessages(['code' => ['Google did not return an authorization code.']]);
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $token->ok()) {
            throw ValidationException::withMessages(['google' => ['Google sign-in could not be completed.']]);
        }

        $accessToken = (string) ($token->json('access_token') ?? '');
        $profile = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if (! $profile->ok()) {
            throw ValidationException::withMessages(['google' => ['Google profile could not be loaded.']]);
        }

        $data = $profile->json();
        $providerId = (string) ($data['sub'] ?? '');
        $email = strtolower((string) ($data['email'] ?? ''));
        $verified = (bool) ($data['email_verified'] ?? false);

        if ($providerId === '' || $email === '') {
            throw ValidationException::withMessages(['google' => ['Google profile did not include account identity.']]);
        }

        $account = SocialAccount::where('provider', 'google')->where('provider_user_id', $providerId)->first();
        $user = $account?->user;

        if (! $user) {
            $user = $verified ? User::where('email', $email)->first() : null;
            $user ??= User::create([
                'name' => (string) ($data['name'] ?? Str::before($email, '@')),
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => $verified ? now() : null,
                'status' => 'active',
            ]);
            $user->roles()->syncWithoutDetaching(Role::firstOrCreate(['name' => 'customer'])->id);
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_user_id' => $providerId,
                'provider_email' => $email,
            ]);
        }

        if ($verified && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($verified) {
            $this->claims->claimForVerifiedUser($user);
        }

        return ['user' => $user->fresh()->load('roles'), 'return_to' => $this->safeReturnTo((string) ($session['return_to'] ?? '/account'))];
    }

    public function safeReturnTo(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH) ?: '/account';
        $query = parse_url($value, PHP_URL_QUERY);
        $allowed = ['/login', '/checkout', '/account'];

        if (! collect($allowed)->contains(fn ($prefix) => $path === $prefix || str_starts_with($path, $prefix.'/'))) {
            $path = '/account';
            $query = null;
        }

        return $path.($query ? '?'.$query : '');
    }

    private function clientId(): string
    {
        $id = (string) config('services.google.client_id');
        if ($id === '') {
            throw ValidationException::withMessages(['google' => ['Google client ID is not configured.']]);
        }

        return $id;
    }

    private function clientSecret(): string
    {
        $secret = (string) config('services.google.client_secret');
        if ($secret === '') {
            throw ValidationException::withMessages(['google' => ['Google client secret is not configured.']]);
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return (string) (config('services.google.redirect_uri') ?: url('/api/v1/auth/google/callback'));
    }
}
