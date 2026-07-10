<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_reviews', function (Blueprint $table) {
            $table->dropUnique('peptide_vendors_reviews_vendor_user_id_unique');
            $table->index(['vendor', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_reviews', function (Blueprint $table) {
            $table->dropIndex(['vendor', 'user_id']);
            $table->unique(['vendor', 'user_id'], 'peptide_vendors_reviews_vendor_user_id_unique');
        });
    }
};
