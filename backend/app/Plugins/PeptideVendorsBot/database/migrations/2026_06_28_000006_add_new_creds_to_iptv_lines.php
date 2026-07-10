<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->string('new_username', 255)->nullable()->after('username');
            $table->string('new_password', 255)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->dropColumn(['new_username', 'new_password']);
        });
    }
};
