<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleOAuthService $google)
    {
        return response()->json(['data' => ['url' => $google->redirectUrl($request)]]);
    }

    public function callback(Request $request, GoogleOAuthService $google)
    {
        try {
            $result = $google->callback($request);
        } catch (ValidationException $exception) {
            return redirect(rtrim((string) config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/').'/login?google=failed');
        }

        return redirect(rtrim((string) config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/').$result['return_to']);
    }
}
