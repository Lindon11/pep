<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->string('telegram_first_name', 255)->nullable()->after('telegram_user_id');
            $table->string('telegram_last_name', 255)->nullable()->after('telegram_first_name');
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->dropColumn(['telegram_first_name', 'telegram_last_name']);
        });
    }
};
