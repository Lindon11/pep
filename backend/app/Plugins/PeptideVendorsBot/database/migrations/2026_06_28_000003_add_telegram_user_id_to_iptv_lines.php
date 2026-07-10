<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->string('telegram_user_id', 64)->nullable()->after('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->dropColumn('telegram_user_id');
        });
    }
};
