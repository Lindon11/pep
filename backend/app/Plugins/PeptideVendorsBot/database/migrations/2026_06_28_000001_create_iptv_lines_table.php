<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_iptv_lines', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('line_id')->nullable();
            $table->string('username', 255);
            $table->string('password', 255);
            $table->string('expire_date', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('speed', 255)->nullable();
            $table->string('con', 255)->nullable();
            $table->string('watching', 255)->nullable();
            $table->string('ip', 255)->nullable();
            $table->string('owner', 255)->nullable();
            $table->string('telegram_username', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_iptv_lines');
    }
};
