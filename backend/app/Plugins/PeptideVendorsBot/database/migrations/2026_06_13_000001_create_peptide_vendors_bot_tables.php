<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_welcome_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64);
            $table->bigInteger('user_id');
            $table->string('first_name', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->text('welcome_message_sent')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'user_id']);
        });

        Schema::create('peptide_vendors_webhook_updates', function (Blueprint $table) {
            $table->id();
            $table->string('update_id', 64)->nullable()->unique();
            $table->string('type', 32)->nullable()->index();
            $table->string('chat_id', 64)->nullable()->index();
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_webhook_updates');
        Schema::dropIfExists('peptide_vendors_welcome_messages');
    }
};
