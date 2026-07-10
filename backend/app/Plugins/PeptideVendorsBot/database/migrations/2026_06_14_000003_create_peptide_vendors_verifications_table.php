<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peptide_vendors_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64);
            $table->string('user_name', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->boolean('agreed')->default(false);
            $table->timestamp('agreed_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peptide_vendors_verifications');
    }
};
