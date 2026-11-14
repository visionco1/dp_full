<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope — مراقبة الطلبات والأخطاء والاستعلامات.
 * الدخول: /telescope لموظف مسجّل دخول في لوحة الإدارة ولديه صلاحية telescope.view (المدير يملكها تلقائياً)
 * — حتى في بيئة local، لأن الخادم قد يعمل بـ APP_ENV=local بالخطأ.
 * التشغيل/الإيقاف: TELESCOPE_ENABLED في .env
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        // على الإنتاج نسجّل المهم فقط (الأخطاء والطلبات الفاشلة) لتخفيف الحمل على قاعدة البيانات
        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });

        // وسم الطلبات بالفرع والقناة لسهولة البحث داخل Telescope
        Telescope::tag(function (IncomingEntry $entry) {
            if ($entry->type !== 'request') {
                return [];
            }
            $r = request();
            return array_values(array_filter([
                $r->is('api/*') ? 'api' : ($r->is('admin', 'admin/*') ? 'admin' : 'web'),
                $r->header('X-Branch-Id') ? 'branch:'.$r->header('X-Branch-Id') : null,
            ]));
        });
    }

    /** لا نسجّل كلمات المرور ورموز التحقق والتوكنات — في كل البيئات */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'code', 'token', 'iban']);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-cart-token',
        ]);

        Telescope::hideResponseParameters(['token', 'debug_code']);
    }

    /** الصلاحية مطلوبة دائماً (نلغي السماح المفتوح الافتراضي في local) */
    protected function authorization(): void
    {
        Telescope::auth(fn (Request $request) => (bool) $request->user()?->hasPermission('telescope.view'));
    }

    protected function gate(): void
    {
        // غير مستخدم — الصلاحية في authorization() أعلاه
    }
}
