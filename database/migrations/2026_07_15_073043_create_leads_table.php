<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tg_user_id')->unique();
            $table->unsignedBigInteger('tg_chat_id');
            $table->string('tg_username')->nullable();
            $table->string('name');
            $table->string('phone', 20)->index();
            $table->string('phone_raw', 50);
            $table->string('company');
            $table->string('source', 30)->default('telegram')->index();
            $table->string('status', 30)->default('new')->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
