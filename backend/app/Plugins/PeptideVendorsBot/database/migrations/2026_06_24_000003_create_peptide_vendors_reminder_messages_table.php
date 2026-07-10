<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_reminder_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64);
            $table->bigInteger('message_id');
            $table->bigInteger('user_id');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_reminder_messages');
    }
};
