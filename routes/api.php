<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\GarageController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
| Mobile API — البادئة /api/v1
| الضيف: السلة عبر الهيدر X-Cart-Token (يرجع في أول استدعاء لـ GET /cart)
| المسجّل: Authorization: Bearer <token>
*/
Route::prefix('v1')->group(function () {
    Route::get('ping', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);

    // المصادقة
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:otp');
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');

    // الكتالوج (عام)
    Route::get('home', [HomeController::class, 'home']);
    Route::get('branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
    Route::get('branches/{branch}', [\App\Http\Controllers\Api\BranchController::class, 'show']);
    Route::get('support/faqs', [HomeController::class, 'faqs']);
    // المحتوى والقيم المعتمدة
    Route::get('pages/{slug}', [\App\Http\Controllers\Api\ContentController::class, 'page'])->where('slug', '[a-z0-9-]+');
    Route::get('bank-accounts', [\App\Http\Controllers\Api\ContentController::class, 'bankAccounts']);
    Route::get('lookups', [\App\Http\Controllers\Api\ContentController::class, 'lookups']);
    Route::get('banners', [CatalogController::class, 'banners']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('categories/{category}/subcategories', [CatalogController::class, 'subcategories']);
    Route::get('grades', [CatalogController::class, 'grades']);
    Route::get('makes', [CatalogController::class, 'makes']);
    Route::get('makes/{make}/models', [CatalogController::class, 'models']);
    Route::get('parts', [CatalogController::class, 'parts']);
    Route::get('parts/{part}', [CatalogController::class, 'part']);

    // السلة والشراء (ضيف أو مسجّل)
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'add']);
    Route::patch('cart/items/{item}', [CartController::class, 'update']);
    Route::delete('cart/items/{item}', [CartController::class, 'remove']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('checkout', [OrderController::class, 'checkout']);

    // تتبع الطلب بدون تسجيل
    Route::post('orders/track', [OrderController::class, 'track'])->middleware('throttle:20,1');
    Route::post('orders/guest-lookup', [OrderController::class, 'guestLookup'])->middleware('throttle:otp');
    Route::get('orders/{number}', [OrderController::class, 'show'])->where('number', 'DM-[0-9-]+');
    Route::post('orders/{number}/receipt', [OrderController::class, 'receipt'])->where('number', 'DM-[0-9-]+');
    Route::post('orders/{number}/cancel', [OrderController::class, 'cancel'])->where('number', 'DM-[0-9-]+');
    Route::get('orders/{number}/invoice', [OrderController::class, 'invoice'])->where('number', 'DM-[0-9-]+');
    Route::get('orders/{number}/returns', [OrderController::class, 'returns'])->where('number', 'DM-[0-9-]+');
    Route::post('orders/{number}/returns', [OrderController::class, 'requestReturn'])->where('number', 'DM-[0-9-]+')->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'update']);
        Route::delete('me', [AuthController::class, 'destroy']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-others', [AuthController::class, 'logoutOthers']);
        Route::get('me/devices', [AuthController::class, 'devices']);
        Route::delete('me/devices/{device}', [AuthController::class, 'deleteDevice'])->whereNumber('device');
        Route::post('me/phone/change-request', [AuthController::class, 'phoneChangeRequest'])->middleware('throttle:otp');
        Route::post('me/phone/change-verify', [AuthController::class, 'phoneChangeVerify'])->middleware('throttle:10,1');
        Route::post('me/device-token', [AuthController::class, 'deviceToken']);

        Route::get('settings', [AccountController::class, 'settings']);
        Route::patch('settings', [AccountController::class, 'updateSettings']);

        Route::get('garage', [GarageController::class, 'index']);
        Route::post('garage', [GarageController::class, 'store']);
        Route::patch('garage/{vehicle}', [GarageController::class, 'update']);
        Route::post('garage/{vehicle}/activate', [GarageController::class, 'activate']);
        Route::delete('garage/{vehicle}', [GarageController::class, 'destroy']);

        Route::get('orders', [OrderController::class, 'index']);

        Route::get('addresses', [AccountController::class, 'addresses']);
        Route::post('addresses', [AccountController::class, 'addAddress']);
        Route::put('addresses/{address}', [AccountController::class, 'updateAddress']);
        Route::delete('addresses/{address}', [AccountController::class, 'deleteAddress']);

        Route::get('notifications', [AccountController::class, 'notifications']);
        Route::post('notifications/read-all', [AccountController::class, 'readAllNotifications']);
        Route::post('notifications/{notification}/read', [AccountController::class, 'readNotification']);

        Route::post('parts/{part}/notify-me', [CatalogController::class, 'notifyMe']);
        Route::delete('parts/{part}/notify-me', [CatalogController::class, 'cancelNotifyMe']);
    });
});
