<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });

        Schema::table('protocols', function (Blueprint $table) {
            $table->json('transcript')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });

        Schema::table('protocols', function (Blueprint $table) {
            $table->longText('transcript')->nullable();
        });

    }
};
