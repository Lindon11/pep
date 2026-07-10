<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_topic_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id');
            $table->string('allowed_user_id', 64);
            $table->string('allowed_user_name', 255)->nullable();
            $table->string('topic_name', 255)->nullable();
            $table->string('added_by', 64)->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'allowed_user_id'], 'pv_tp_topic_user_unique');
            $table->index('topic_id', 'pv_tp_topic_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_topic_permissions');
    }
};
