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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
            $table->string('phone_raw', 50)->nullable()->change();
            $table->string('company')->nullable()->change();
            $table->string('capture_step', 30)->default('completed')->index()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['capture_step']);
            $table->dropColumn('capture_step');
            $table->string('name')->nullable(false)->change();
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('phone_raw', 50)->nullable(false)->change();
            $table->string('company')->nullable(false)->change();
        });
    }
};
