# دفع المحركات — Laravel (API للموبايل + لوحة التحكم + نقطة البيع)

> ⚠️ **لم يُشغَّل هذا المشروع ولم تُنفَّذ اختباراته** في بيئة الإنشاء (لا يوجد وصول لـ packagist). فُحصت كل ملفات PHP بـ `php -l` فقط، وملفات Blade لم تُفحص. شغّل `php artisan test` بعد التثبيت وأبلغني بأي خطأ.

## التشغيل (مشروع Laravel 12 كامل)
```bash
composer install
cp .env.example .env          # على ويندوز: copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite    # لينكس/ماك: touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
- المتطلبات: PHP 8.2+ بامتدادات sqlite/mbstring/zip/gd، وComposer.
- اللوحة: `http://127.0.0.1:8000/admin` — `admin@store.example` / `password` (مدير)، `omar.pos@store.example` (نقطة بيع)، `orders@store.example` (طلبات). **غيّر كلمات المرور فوراً.**
- الـ API: `http://127.0.0.1:8000/api/v1` — استورد `docs/dp-store.postman_collection.json`.
- عميل تجريبي: `966512345678`.
- الاختبارات: `php artisan test`.

## الموقع (واجهة العملاء)
الصفحة الرئيسية `/` : كتالوج بفلاتر (الماركة/الموديل/السنة/الفئة/الجودة/المتوفر)، صفحة القطعة، السلة، الشراء كضيف (عند الاستلام أو تحويل بنكي مع رفع الإيصال)، وتتبّع الطلب برقم الطلب + الجوال `/track`. لم يُنفَّذ بعد في الموقع: تسجيل دخول العميل بـ OTP والجراج والعناوين المحفوظة (متاحة في الـ API).

## الأساسيات
- الدخول بالجوال + OTP (4 أرقام، 5 دقائق، 3 محاولات، إعادة إرسال بعد 59 ث). مع `DP_OTP_DEBUG=true` يرجع `debug_code` في الاستجابة — **أوقفه في الإنتاج**.
- مزوّد الرسائل: `App\Contracts\SmsGateway` — الافتراضي `log` (يكتب في storage/logs). اربط مزوّدك (Unifonic/Taqnyat…) بإنشاء كلاس جديد وتعديل الـ binding في `AppServiceProvider`.
- السلة للضيف: هيدر `X-Cart-Token` (يرجع في `GET /cart`). عند تسجيل الدخول أرسل نفس الهيدر ليُدمج.
- المصادقة: `Authorization: Bearer <token>`.
- الأسعار شاملة 15% ضريبة؛ الشحن 25 + رسوم القطع الكبيرة (`config/dp.php`).
- المخزون: الطلب الأونلاين يحجز، الشحن يخصم فعلياً، الإلغاء يحرّر الحجز، نقطة البيع تخصم فوراً.

## أهم المسارات (`/api/v1`)
المصادقة: `auth/register`، `auth/otp/request`، `auth/otp/verify`، `auth/logout` · الكتالوج: `categories`، `makes`، `makes/{id}/models`، `parts?q=&category=&fit_only=1` · السلة: `cart`، `cart/items` · الدفع: `POST checkout` (`cod|bank`) · الطلبات: `orders`، `orders/{no}`، `orders/track`، `orders/{no}/receipt`، `orders/{no}/cancel`، `orders/{no}/invoice` · الحساب: `me`، `garage`، `addresses`، `notifications`، `settings`، `parts/{id}/notify-me`، `me/device-token`.
أخطاء التحقق 422 برسائل عربية بصيغة Laravel القياسية.

## لوحة التحكم
نظرة عامة، نقطة البيع، المخزون (تعديل سريع + استيراد CSV/XLSX بمعاينة)، الكتالوج والتوافق، الطلبات (اعتماد/رفض التحويل، تحويل الحالات، طباعة بوالص وفواتير فردية/جماعية)، الصلاحيات والموظفون، التقارير (Excel/CSV مع إخفاء جزئي لأرقام العملاء لمن لا يملك `reports.customers`).

## غير مُنجز / للإنتاج
- إشعارات FCM: مكان التنفيذ `NotificationService` (الرموز تُحفظ عبر `me/device-token`).
- بحث الضيف عن طلباته برقم الجوال بـ OTP لم يُنفَّذ (التتبع برقم الطلب + الجوال متاح).
- الفاتورة HTML قابلة للطباعة (بدون PDF/QR زاتكا) — أضف حزمة ZATCA عند الحاجة.
- اضبط `DP_SELLER_VAT`، قاعدة بيانات حقيقية (MySQL/PostgreSQL)، `APP_DEBUG=false`، تخزين الإيصالات، والنسخ الاحتياطي.
