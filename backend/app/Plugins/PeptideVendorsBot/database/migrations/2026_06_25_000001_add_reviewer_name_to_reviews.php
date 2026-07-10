<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_reviews', function (Blueprint $table) {
            $table->string('reviewer_name', 255)->nullable()->after('user_name');
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_reviews', function (Blueprint $table) {
            $table->dropColumn('reviewer_name');
        });
    }
};
