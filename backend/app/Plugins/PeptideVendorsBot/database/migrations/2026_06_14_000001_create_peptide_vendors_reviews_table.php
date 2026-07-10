<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('vendor', 255);
            $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->string('user_id', 64);
            $table->string('user_name', 255)->nullable();
            $table->string('chat_id', 64);
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();

            $table->unique(['vendor', 'user_id']);
            $table->index('vendor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_reviews');
    }
};
