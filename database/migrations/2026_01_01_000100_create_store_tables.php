<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 20)->index();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->json('payload')->nullable();   // بيانات التسجيل المعلّقة
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name_ar');
            $t->string('name_en')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('makes', function (Blueprint $t) {
            $t->id();
            $t->string('name_ar');
            $t->string('name_en');
            $t->timestamps();
        });

        Schema::create('car_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('make_id')->constrained()->cascadeOnDelete();
            $t->string('name_ar');
            $t->string('name_en');
            $t->unsignedSmallInteger('year_from')->default(2000);
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->timestamps();
        });

        Schema::create('parts', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();                 // رقم القطعة OEM
            $t->string('name_ar');
            $t->string('name_en')->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('brand')->nullable();
            $t->string('grade')->default('oem');         // oem | cert | first
            $t->string('side', 1)->nullable();           // R | L
            $t->string('group_key')->nullable()->index();// يربط يمين/يسار والدرجات لنفس القطعة
            $t->decimal('price', 10, 2);                 // شامل الضريبة
            $t->decimal('cost', 10, 2)->default(0);
            $t->boolean('is_bulky')->default(false);
            $t->decimal('shipping_fee', 8, 2)->default(0);
            $t->string('image')->nullable();
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index('name_ar');
        });

        Schema::create('part_fitments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year_from');
            $t->unsignedSmallInteger('year_to');
            $t->timestamps();
            $t->index(['car_model_id', 'year_from', 'year_to']);
        });

        Schema::create('inventories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_id')->unique()->constrained()->cascadeOnDelete();
            $t->integer('on_hand')->default(0);
            $t->integer('reserved')->default(0);
            $t->unsignedInteger('low_stock_at')->default(3);
            $t->string('location')->nullable();
            $t->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->integer('qty');                      // موجب = إضافة
            $t->string('type');                      // sale_pos | sale_online | adjust | import | return
            $t->string('reference')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('stock_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'part_id']);
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->string('engine')->nullable();
            $t->string('vin', 17)->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });

        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->string('name');
            $t->string('phone', 20);
            $t->string('city');
            $t->string('line');
            $t->string('national_address', 8)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('token', 64)->nullable()->unique();
            $t->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('qty')->default(1);
            $t->timestamps();
            $t->unique(['cart_id', 'part_id']);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();                  // DM-YYMMDD-NNNN
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name');
            $t->string('customer_phone', 20)->index();
            $t->string('channel')->default('app');           // app | web | pos
            $t->string('status')->default('new');            // new|pending_transfer|processing|shipped|completed|cancelled
            $t->string('payment_method');                    // cod | bank | cash | card
            $t->string('payment_status')->default('unpaid'); // unpaid | pending | paid | rejected
            $t->decimal('subtotal', 10, 2);                  // شامل الضريبة
            $t->decimal('vat', 10, 2);
            $t->decimal('shipping', 10, 2)->default(0);
            $t->decimal('total', 10, 2);
            $t->json('address')->nullable();
            $t->string('receipt_path')->nullable();
            $t->string('waybill_no')->nullable();
            $t->string('reject_reason')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('placed_at')->useCurrent();
            $t->timestamps();
            $t->index(['status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sku');
            $t->string('name');
            $t->decimal('unit_price', 10, 2);
            $t->decimal('unit_cost', 10, 2)->default(0);
            $t->decimal('shipping_fee', 8, 2)->default(0);
            $t->unsignedSmallInteger('qty');
            $t->decimal('line_total', 10, 2);
            $t->timestamps();
        });

        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('status');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type');                 // order | stock | price | offer
            $t->string('title');
            $t->text('body')->nullable();
            $t->json('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('device_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token')->unique();
            $t->string('platform', 10)->default('android');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['device_tokens','app_notifications','order_events','order_items','orders','cart_items','carts','addresses','vehicles','stock_alerts','stock_movements','inventories','part_fitments','parts','car_models','makes','categories','otp_codes'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
