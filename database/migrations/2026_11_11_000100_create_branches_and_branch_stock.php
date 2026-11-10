<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الفروع + مخزون لكل فرع:
 * - جدول branches وإنشاء "الفرع الرئيسي" ونقل كل المخزون الحالي إليه
 * - inventories: سطر لكل (قطعة × فرع) بدل سطر واحد لكل قطعة
 * - orders: الفرع المنفّذ + طريقة الاستلام (توصيل/استلام من الفرع)
 * - users: فرع الموظف (نقطة البيع والمخزون)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('code', 20)->unique();
            $t->string('city', 60);
            $t->string('address')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('hours', 120)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->boolean('pickup_enabled')->default(true);
            $t->boolean('delivery_enabled')->default(true);
            $t->boolean('is_default')->default(false);
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        $main = DB::table('branches')->insertGetId([
            'name' => 'الفرع الرئيسي', 'code' => 'MAIN', 'city' => 'الرياض', 'is_default' => true, 'active' => true,
            'hours' => 'السبت – الخميس 9 ص – 10 م', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // المخزون: نضيف الفهرس المركّب أولاً (يخدم مفتاح part_id الأجنبي) ثم نحذف القديم
        Schema::table('inventories', function (Blueprint $t) {
            $t->foreignId('branch_id')->nullable()->after('part_id')->constrained()->restrictOnDelete();
        });
        DB::table('inventories')->update(['branch_id' => $main]);
        Schema::table('inventories', function (Blueprint $t) {
            $t->unique(['part_id', 'branch_id']);
        });
        Schema::table('inventories', function (Blueprint $t) {
            $t->dropUnique(['part_id']);
        });

        Schema::table('stock_movements', function (Blueprint $t) {
            $t->foreignId('branch_id')->nullable()->after('part_id')->constrained()->nullOnDelete();
        });
        DB::table('stock_movements')->update(['branch_id' => $main]);

        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('branch_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $t->string('fulfillment', 10)->default('delivery')->after('channel');   // delivery | pickup
            $t->index(['branch_id', 'status']);
        });
        DB::table('orders')->update(['branch_id' => $main]);
        DB::table('orders')->where('channel', 'pos')->update(['fulfillment' => 'pickup']);

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['branch_id', 'status']);
            $t->dropConstrainedForeignId('branch_id');
            $t->dropColumn('fulfillment');
        });
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));

        // نجمع مخزون كل الفروع في سطر واحد لكل قطعة قبل الرجوع
        $sums = DB::table('inventories')->selectRaw('part_id, SUM(on_hand) on_hand, SUM(reserved) reserved, MIN(id) keep_id')->groupBy('part_id')->get();
        foreach ($sums as $s) {
            DB::table('inventories')->where('id', $s->keep_id)->update(['on_hand' => $s->on_hand, 'reserved' => $s->reserved]);
            DB::table('inventories')->where('part_id', $s->part_id)->where('id', '!=', $s->keep_id)->delete();
        }
        Schema::table('inventories', fn (Blueprint $t) => $t->unique('part_id'));
        Schema::table('inventories', function (Blueprint $t) {
            $t->dropUnique(['part_id', 'branch_id']);
            $t->dropConstrainedForeignId('branch_id');
        });
        Schema::dropIfExists('branches');
    }
};
