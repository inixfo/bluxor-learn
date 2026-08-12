<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LandingPageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

Route::get('/', function () {
    return response()->json([
        'data' => [
            'name' => config('app.name'),
            'status' => 'ok',
        ],
    ]);
});

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));

Route::get('/health/ready', function () {
    $checks = ['database' => false, 'redis' => false];

    try {
        DB::select('select 1');
        $checks['database'] = true;
    } catch (Throwable) {
        //
    }

    try {
        $checks['redis'] = (bool) Redis::connection()->ping();
    } catch (Throwable) {
        //
    }

    return response()->json(['data' => ['status' => in_array(false, $checks, true) ? 'degraded' : 'ready', 'checks' => $checks]], in_array(false, $checks, true) ? 503 : 200);
});

Route::get('/reset-password/{token}', function (string $token) {
    $query = http_build_query([
        'token' => $token,
        'email' => request('email'),
    ]);

    return redirect(rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/reset-password?'.$query);
})->name('password.reset');

Route::get('/landing-runtime/lbx-runtime.v2.js', [LandingPageController::class, 'runtime'])->name('landing.runtime');
Route::get('/go/{slug}', [LandingPageController::class, 'serve'])->where('slug', '[A-Za-z0-9-]+')->name('landing.serve');
Route::get('/go/{slug}/{path}', [LandingPageController::class, 'asset'])->where(['slug' => '[A-Za-z0-9-]+', 'path' => '.*'])->name('landing.asset');
Route::get('/lp/{slug}', fn (string $slug) => redirect('/go/'.$slug, 301))->where('slug', '[A-Za-z0-9-]+');
Route::get('/lp/{slug}/{path}', fn (string $slug, string $path) => redirect('/go/'.$slug.'/'.$path, 301))->where(['slug' => '[A-Za-z0-9-]+', 'path' => '.*']);
Route::get('/landing-preview/{version}', [LandingPageController::class, 'preview'])->name('landing.preview');
Route::get('/landing-preview/{version}/{path}', [LandingPageController::class, 'previewAsset'])->where('path', '.*')->name('landing.preview.asset');
