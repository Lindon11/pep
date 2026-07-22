<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peptide_vendors_iptv_lines') && !Schema::hasColumn('peptide_vendors_iptv_lines', 'can_dm')) {
            Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
                $table->boolean('can_dm')->default(false)->after('linked_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->dropColumn('can_dm');
        });
    }
};
