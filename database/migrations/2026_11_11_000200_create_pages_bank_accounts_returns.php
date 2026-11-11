<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** صفحات المحتوى (الشروط/الخصوصية/الإرجاع) + الحسابات البنكية + طلبات الإرجاع والاستبدال */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('title', 150);
            $t->longText('content');
            $t->timestamps();
        });

        $now = now();
        DB::table('pages')->insert([
            ['slug' => 'terms', 'title' => 'الشروط والأحكام', 'content' => "باستخدامك تطبيق ومتجر دفع المحركات فإنك توافق على هذه الشروط.\n\n1. الأسعار المعروضة بالريال السعودي وشاملة ضريبة القيمة المضافة.\n2. يلتزم العميل بالتأكد من توافق القطعة مع سيارته باستخدام خاصية الكراج أو رقم الهيكل.\n3. الطلب يُعتبر مؤكداً بعد استلام الدفع أو اختيار الدفع عند الاستلام.\n4. نحتفظ بحق إلغاء الطلب في حال عدم توفر القطعة مع إعادة المبلغ كاملاً.\n\n(عدّل هذا النص من لوحة الإدارة ← الصفحات)", 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'privacy', 'title' => 'سياسة الخصوصية', 'content' => "نحترم خصوصيتك ونحمي بياناتك.\n\n- نجمع الاسم ورقم الجوال والعنوان لتنفيذ طلباتك فقط.\n- لا نشارك بياناتك مع أي طرف ثالث إلا شركة الشحن لتوصيل طلبك.\n- يمكنك طلب حذف حسابك في أي وقت من الإعدادات.\n\n(عدّل هذا النص من لوحة الإدارة ← الصفحات)", 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'returns-policy', 'title' => 'سياسة الإرجاع والاستبدال', 'content' => "- يمكن طلب الإرجاع أو الاستبدال خلال 14 يوماً من استلام الطلب.\n- يشترط ألا تكون القطعة قد رُكّبت، وأن تكون في تغليفها الأصلي.\n- القطع الأصلية مضمونة لمدة سنة ضد عيوب التصنيع.\n- يُعاد المبلغ بنفس طريقة الدفع خلال 7 أيام عمل من استلام القطعة المرتجعة.\n\n(عدّل هذا النص من لوحة الإدارة ← الصفحات)", 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('bank_name', 80);
            $t->string('beneficiary_name', 120);
            $t->string('iban', 34);
            $t->string('account_number', 30)->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 10);                       // return | exchange
            $t->unsignedSmallInteger('qty')->default(1);
            $t->string('reason', 300);
            $t->string('image')->nullable();
            $t->string('status', 12)->default('pending');   // pending | approved | rejected | completed
            $t->string('admin_note', 300)->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('pages');
    }
};
