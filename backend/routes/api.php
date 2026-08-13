<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminContentPageController;
use App\Http\Controllers\Api\AdminEmailController;
use App\Http\Controllers\Api\AdminLandingPageController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentPageController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PublicCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('v1')->group(function () {
    Route::get('/home', [PublicCatalogController::class, 'home']);
    Route::get('/products', [PublicCatalogController::class, 'products']);
    Route::get('/categories', [PublicCatalogController::class, 'categories']);
    Route::get('/content-pages/{slug}', [ContentPageController::class, 'show']);
    Route::post('/contact', [ContactController::class, 'submit'])->middleware('throttle:5,1');
    Route::get('/catalog/{slug}', [PublicCatalogController::class, 'catalog']);
    Route::get('/search/products', [PublicCatalogController::class, 'search']);
    Route::get('/landing-pages/{slug}/context', [LandingPageController::class, 'context']);
    Route::post('/analytics/events', [LandingPageController::class, 'track'])->middleware('throttle:120,1');

    Route::get('/auth/csrf-cookie', fn () => response()->json(['data' => ['ok' => true]]));
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:10,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:3,1']);
    Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('throttle:6,1')->name('verification.verify');

    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:30,1');
    Route::post('/checkout/orders', [CheckoutController::class, 'createOrder'])->middleware('throttle:12,1');
    Route::get('/checkout/orders/{orderNumber}/receipt', [CheckoutController::class, 'receipt']);

    Route::get('/guest/orders/{orderNumber}', [AccountController::class, 'guestOrder'])->middleware('throttle:30,1');
    Route::get('/guest/downloads/{file}/{entitlement}', [AccountController::class, 'serveGuestDownload'])->middleware('throttle:60,1')->name('downloads.guest');

    Route::post('/payments/piprapay/initiate', [PaymentController::class, 'initiatePipraPay'])->middleware('throttle:12,1');
    Route::match(['get', 'post'], '/payments/piprapay/success', [PaymentController::class, 'success'])->middleware('throttle:30,1');
    Route::match(['get', 'post'], '/payments/piprapay/cancel', [PaymentController::class, 'failed'])->middleware('throttle:30,1');
    Route::post('/payments/piprapay/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:120,1');

    Route::middleware('auth')->prefix('account')->group(function () {
        Route::get('/overview', [AccountController::class, 'overview']);
        Route::get('/library', [AccountController::class, 'library']);
        Route::get('/library/{productId}', [AccountController::class, 'libraryDetail']);
        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{orderNumber}', [AccountController::class, 'orderDetail']);
        Route::get('/downloads', [AccountController::class, 'downloads']);
        Route::get('/profile', [AccountController::class, 'profile']);
        Route::patch('/profile', [AccountController::class, 'updateProfile']);
        Route::put('/password', [AccountController::class, 'updatePassword']);
        Route::post('/downloads/{file}', [AccountController::class, 'download']);
        Route::get('/downloads/{file}/{entitlement}', [AccountController::class, 'serveCustomerDownload'])->name('downloads.customer');
    });

    Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::get('/products/{product}', [AdminController::class, 'showProduct']);
        Route::patch('/products/{product}', [AdminController::class, 'updateProduct']);
        Route::post('/products/{product}', [AdminController::class, 'updateProduct']);
        Route::post('/products/{product}/publish', [AdminController::class, 'publishProduct']);
        Route::post('/products/{product}/archive', [AdminController::class, 'archiveProduct']);
        Route::post('/products/{product}/files', [AdminController::class, 'uploadProductFile']);
        Route::apiResource('categories', AdminCategoryController::class);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::post('/orders/{order}/refund', [PaymentController::class, 'refund'])->middleware('throttle:6,1');
        Route::get('/customers', [AdminController::class, 'customers']);
        Route::get('/offer-items', [AdminController::class, 'offerItems']);
        Route::get('/coupons', [AdminController::class, 'coupons']);
        Route::post('/coupons', [AdminController::class, 'storeCoupon']);
        Route::get('/coupons/{coupon}', [AdminController::class, 'showCoupon']);
        Route::patch('/coupons/{coupon}', [AdminController::class, 'updateCoupon']);
        Route::post('/coupons/{coupon}/pause', [AdminController::class, 'pauseCoupon']);
        Route::delete('/coupons/{coupon}', [AdminController::class, 'archiveCoupon']);
        Route::get('/analytics/summary', [AdminController::class, 'analytics']);
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
        Route::get('/audit-logs/{auditLog}', [AdminController::class, 'auditLog']);
        Route::get('/landing-pages', [AdminLandingPageController::class, 'index']);
        Route::post('/landing-pages/uploads', [AdminLandingPageController::class, 'upload']);
        Route::get('/landing-pages/{landingPage}', [AdminLandingPageController::class, 'show']);
        Route::patch('/landing-pages/{landingPage}/offers', [AdminLandingPageController::class, 'assignOffers']);
        Route::patch('/landing-pages/{landingPage}/product', [AdminLandingPageController::class, 'updateProduct']);
        Route::post('/landing-pages/{landingPage}/versions/{version}/publish', [AdminLandingPageController::class, 'publish']);
        Route::post('/landing-pages/{landingPage}/unpublish', [AdminLandingPageController::class, 'unpublish']);
        Route::get('/landing-page-versions/{version}/preview-url', [AdminLandingPageController::class, 'previewUrl']);
        Route::get('/landing-page-versions/{version}/download', [AdminLandingPageController::class, 'download']);
        Route::get('/landing-pages/{landingPage}/analytics', [AdminLandingPageController::class, 'analytics']);
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::patch('/settings/{section}', [AdminController::class, 'updateSettings']);
        Route::post('/settings/email/test', [AdminEmailController::class, 'test'])->middleware('throttle:6,1');
        Route::apiResource('content-pages', AdminContentPageController::class)->except(['destroy']);
        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read']);
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'readAll']);
    });
});
