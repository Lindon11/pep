<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->timestamp('linked_at')->nullable()->after('telegram_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->dropColumn('linked_at');
        });
    }
};
