<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->json('permissions');          // ["orders.view", ...] أو ["*"]
            $t->boolean('locked')->default(false);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 20)->nullable()->unique();   // بصيغة دولية بدون + مثل 966512345678
            $t->string('email')->nullable()->unique();       // للموظفين
            $t->string('password')->nullable();               // للموظفين فقط
            $t->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $t->boolean('is_staff')->default(false);
            $t->boolean('active')->default(true);
            $t->string('type')->default('individual');        // individual | business
            $t->string('business_name')->nullable();
            $t->string('activity')->nullable();               // workshop | center | shop
            $t->string('cr_number', 10)->nullable();
            $t->string('vat_number', 15)->nullable();
            $t->string('cr_document')->nullable();
            $t->string('business_status')->nullable();        // pending | verified
            $t->string('city')->nullable();
            $t->string('locale', 5)->default('ar');
            $t->string('theme', 10)->default('light');
            $t->json('notif_prefs')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->timestamp('deletion_requested_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
