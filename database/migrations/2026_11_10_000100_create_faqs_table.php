<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('category', 40)->nullable();   // تجميع اختياري: الطلبات، الدفع، الضمان...
            $t->string('question', 250);
            $t->text('answer');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
