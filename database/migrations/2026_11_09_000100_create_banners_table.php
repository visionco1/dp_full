<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // البانرات والإعلانات: تظهر في الموقع والتطبيق حسب المكان (placement)
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('placement', 20)->index();   // hero | strip | side | topbar
            $t->string('title', 120);
            $t->string('subtitle', 250)->nullable();
            $t->string('image')->nullable();
            $t->string('link_url')->nullable();
            $t->string('button_text', 40)->nullable();
            $t->string('bg_color', 7)->default('#111111');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
