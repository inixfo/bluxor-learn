<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminDiagnosticMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminEmailController extends Controller
{
    public function test(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            Mail::to($data['email'])->queue(new AdminDiagnosticMail());
        } catch (\Throwable $exception) {
            Log::warning('SMTP diagnostic email failed.', ['message' => $exception->getMessage()]);
            throw ValidationException::withMessages(['email' => ['Test email could not be sent. Check SMTP configuration and logs.']]);
        }

        return response()->json(['data' => ['message' => 'Test email queued.']]);
    }
}
