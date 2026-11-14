<?php

return [
    'vat_rate' => 0.15,                 // الأسعار المخزّنة شاملة الضريبة
    'base_shipping' => 25.00,           // رسوم الشحن الأساسية للطلب
    'currency' => 'SAR',
    'seller_vat_number' => env('DP_SELLER_VAT', '[الرقم الضريبي]'),
    'seller_name' => 'دفع المحركات — Engine Power & More',
    'return_days' => (int) env('DP_RETURN_DAYS', 14),   // مهلة طلب الإرجاع/الاستبدال بعد اكتمال الطلب
    'seller_cr_number' => env('DP_SELLER_CR', '[رقم السجل التجاري]'),

    // اقتراحات البحث في الصفحة الرئيسية للتطبيق (تُكمَّل تلقائياً بالأكثر طلباً)
    'search_suggestions' => ['فلتر زيت', 'فحمات فرامل', 'بواجي', 'بطارية', 'مساعدات'],

    // بيانات التواصل في شاشة الدعم
    'support' => [
        'phone' => env('DP_SUPPORT_PHONE'),
        'whatsapp' => env('DP_SUPPORT_WHATSAPP'),
        'email' => env('DP_SUPPORT_EMAIL'),
        'hours' => env('DP_SUPPORT_HOURS', 'السبت – الخميس، 9 صباحاً – 10 مساءً'),
    ],

    'otp' => [
        'length' => 4,
        'ttl_minutes' => 5,
        'max_attempts' => 3,
        'resend_seconds' => 59,
        'debug' => env('DP_OTP_DEBUG', false),   // لا تفعّله في الإنتاج
    ],

    'sms_driver' => env('DP_SMS_DRIVER', 'log'),

    // رمز الدولة => [طول الرقم الوطني, أول رقم مطلوب]
    'countries' => [
        '966' => ['len' => 9, 'start' => '5'],
        '971' => ['len' => 9, 'start' => '5'],
        '965' => ['len' => 8, 'start' => null],
        '973' => ['len' => 8, 'start' => null],
        '974' => ['len' => 8, 'start' => null],
        '968' => ['len' => 8, 'start' => null],
    ],

    'order_statuses' => [
        'new' => 'جديد',
        'pending_transfer' => 'بانتظار التحقق من التحويل البنكي',
        'processing' => 'قيد التجهيز',
        'ready' => 'جاهز للاستلام من الفرع',
        'shipped' => 'تم الشحن',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    // كل الصلاحيات المتاحة مجمّعة للعرض في شاشة الأدوار
    'permissions' => [
        'dashboard.view' => 'عرض لوحة التحكم',
        'dashboard.financials' => 'عرض الأرقام المالية والأرباح',
        'pos.use' => 'استخدام نقطة البيع',
        'inventory.view' => 'عرض المخزون',
        'inventory.edit' => 'تعديل الكميات والأسعار',
        'inventory.cost' => 'رؤية سعر التكلفة والهامش',
        'inventory.import' => 'استيراد Excel/CSV',
        'catalog.manage' => 'إدارة القطع والتوافق',
        'banners.manage' => 'إدارة البانرات والإعلانات',
        'support.manage' => 'إدارة الأسئلة الشائعة والصفحات والحسابات البنكية',
        'branches.manage' => 'إدارة الفروع وتحويل المخزون بينها',
        'orders.view' => 'عرض الطلبات',
        'orders.manage' => 'تغيير حالة الطلب والإلغاء',
        'orders.verify' => 'اعتماد/رفض التحويل البنكي',
        'orders.print' => 'طباعة البوالص والفواتير',
        'reports.export' => 'تصدير التقارير',
        'reports.customers' => 'عرض أرقام العملاء كاملة',
        'roles.manage' => 'إدارة الموظفين والصلاحيات',
        'telescope.view' => 'مراقبة النظام (Telescope): الطلبات والأخطاء والاستعلامات',
    ],
];
