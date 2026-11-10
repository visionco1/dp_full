<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // الأقسام الفرعية: قسم له parent_id يكون فرعياً تحت القسم الرئيسي (مستوى واحد فقط)
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
        });
    }
};
