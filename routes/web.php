<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BranchController as AdminBranchController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TaxonomyController;
use App\Http\Controllers\Web\ShopController;
use Illuminate\Support\Facades\Route;

// واجهة العملاء
Route::name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::get('parts/{part}', [ShopController::class, 'part'])->name('part');
    Route::get('cart', [ShopController::class, 'cartShow'])->name('cart');
    Route::post('cart', [ShopController::class, 'cartAdd'])->name('cart.add');
    Route::patch('cart/{item}', [ShopController::class, 'cartUpdate'])->name('cart.update');
    Route::get('checkout', [ShopController::class, 'checkout'])->name('checkout');
    Route::post('branch', [ShopController::class, 'setBranch'])->name('branch');
    Route::post('checkout', [ShopController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
    Route::get('track', [ShopController::class, 'track'])->name('track');
    Route::post('track', [ShopController::class, 'trackFind'])->middleware('throttle:20,1')->name('track.find');
    Route::get('orders/{number}', [ShopController::class, 'order'])->where('number', 'DM-[0-9-]+')->name('order');
    Route::post('orders/{number}/receipt', [ShopController::class, 'receipt'])->where('number', 'DM-[0-9-]+')->name('order.receipt');
    Route::post('orders/{number}/cancel', [ShopController::class, 'cancel'])->where('number', 'DM-[0-9-]+')->name('order.cancel');
    Route::get('go/{banner}', [ShopController::class, 'bannerClick'])->name('banner');
});

Route::get('admin/login', [AuthController::class, 'form'])->name('login');
Route::post('admin/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');

    // نقطة البيع
    Route::middleware('perm:pos.use')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('search', [PosController::class, 'search'])->name('search');
        Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('receipt/{order}', [PosController::class, 'receipt'])->name('receipt');
    });

    // المخزون
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->middleware('perm:inventory.view')->name('index');
        Route::get('export', [InventoryController::class, 'export'])->middleware('perm:inventory.view')->name('export');
        Route::patch('{part}', [InventoryController::class, 'update'])->middleware('perm:inventory.edit')->name('update');
        Route::middleware('perm:inventory.import')->group(function () {
            Route::get('template', [InventoryController::class, 'template'])->name('template');
            Route::post('import/preview', [InventoryController::class, 'importPreview'])->name('import.preview');
            Route::post('import/apply', [InventoryController::class, 'importApply'])->name('import.apply');
        });
    });

    // الكتالوج والتوافق
    Route::middleware('perm:catalog.manage')->prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])->name('index');
        Route::get('create', [CatalogController::class, 'create'])->name('create');
        Route::post('/', [CatalogController::class, 'store'])->name('store');
        Route::get('makes/{make}/models', [CatalogController::class, 'models'])->name('models');

        // الأقسام
        Route::get('categories', [TaxonomyController::class, 'categories'])->name('categories.index');
        Route::post('categories', [TaxonomyController::class, 'storeCategory'])->name('categories.store');
        Route::put('categories/{category}', [TaxonomyController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [TaxonomyController::class, 'destroyCategory'])->name('categories.destroy');

        // الماركات والموديلات
        Route::get('makes', [TaxonomyController::class, 'makes'])->name('makes.index');
        Route::post('makes', [TaxonomyController::class, 'storeMake'])->name('makes.store');
        Route::put('makes/{make}', [TaxonomyController::class, 'updateMake'])->name('makes.update');
        Route::delete('makes/{make}', [TaxonomyController::class, 'destroyMake'])->name('makes.destroy');
        Route::post('makes/{make}/models', [TaxonomyController::class, 'storeModel'])->name('makes.models.store');
        Route::put('models/{model}', [TaxonomyController::class, 'updateModel'])->name('makes.models.update');
        Route::delete('models/{model}', [TaxonomyController::class, 'destroyModel'])->name('makes.models.destroy');

        Route::get('{part}/edit', [CatalogController::class, 'edit'])->name('edit');
        Route::put('{part}', [CatalogController::class, 'update'])->name('update');
        Route::delete('{part}', [CatalogController::class, 'destroy'])->name('destroy');
        Route::post('{part}/fitments', [CatalogController::class, 'addFitment'])->name('fitments.add');
        Route::post('{part}/fitments/copy', [CatalogController::class, 'copyFitments'])->name('fitments.copy');
        Route::delete('{part}/fitments/{fitment}', [CatalogController::class, 'removeFitment'])->name('fitments.remove');
    });

    // البانرات والإعلانات
    Route::middleware('perm:banners.manage')->prefix('banners')->name('banners.')->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::put('{banner}', [BannerController::class, 'update'])->name('update');
        Route::post('{banner}/toggle', [BannerController::class, 'toggle'])->name('toggle');
        Route::delete('{banner}', [BannerController::class, 'destroy'])->name('destroy');
    });

    // الفروع
    Route::post('branch', [AdminBranchController::class, 'switch'])->name('branch.switch');
    Route::middleware('perm:branches.manage')->prefix('branches')->name('branches.')->group(function () {
        Route::get('/', [AdminBranchController::class, 'index'])->name('index');
        Route::post('/', [AdminBranchController::class, 'store'])->name('store');
        Route::post('transfer', [AdminBranchController::class, 'transfer'])->name('transfer');
        Route::put('{branch}', [AdminBranchController::class, 'update'])->name('update');
        Route::post('{branch}/default', [AdminBranchController::class, 'makeDefault'])->name('default');
        Route::delete('{branch}', [AdminBranchController::class, 'destroy'])->name('destroy');
    });

    // الأسئلة الشائعة
    Route::middleware('perm:support.manage')->prefix('faqs')->name('faqs.')->group(function () {
        Route::get('/', [FaqController::class, 'index'])->name('index');
        Route::post('/', [FaqController::class, 'store'])->name('store');
        Route::put('{faq}', [FaqController::class, 'update'])->name('update');
        Route::delete('{faq}', [FaqController::class, 'destroy'])->name('destroy');
    });

    // الطلبات
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('perm:orders.view')->name('index');
        Route::get('print', [OrderController::class, 'print'])->middleware('perm:orders.print')->name('print');
        Route::get('{order}', [OrderController::class, 'show'])->middleware('perm:orders.view')->name('show');
        Route::post('{order}/approve', [OrderController::class, 'approve'])->middleware('perm:orders.verify')->name('approve');
        Route::post('{order}/reject', [OrderController::class, 'reject'])->middleware('perm:orders.verify')->name('reject');
        Route::post('{order}/{to}', [OrderController::class, 'transition'])->middleware('perm:orders.manage')->whereIn('to', ['processing', 'ready', 'ship', 'complete', 'cancel'])->name('transition');
    });

    // الصلاحيات
    Route::middleware('perm:roles.manage')->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
        Route::post('staff', [RoleController::class, 'storeStaff'])->name('staff.store');
        Route::put('staff/{user}', [RoleController::class, 'updateStaff'])->name('staff.update');
    });

    // التقارير
    Route::middleware('perm:reports.export')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('export/{type}', [ReportController::class, 'export'])->name('export');
    });
});
