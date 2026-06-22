<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Zapotrzebowania', function (Blueprint $table) {
            // Część dodana przez technika poza pierwotnym zleceniem (doliczana do kosztu)
            $table->boolean('dodatkowa')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('Zapotrzebowania', function (Blueprint $table) {
            $table->dropColumn('dodatkowa');
        });
    }
};
