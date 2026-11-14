<?php

use Illuminate\Support\Facades\Schedule;

// حذف سجلات Telescope الأقدم من 48 ساعة يومياً (حتى لا يكبر الجدول) — يتطلب تشغيل php artisan schedule:run كل دقيقة على الخادم
Schedule::command('telescope:prune --hours=48')->daily();
