<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Obsługa wielu zdjęć dla jednego zlecenia
        Schema::create('ZdjeciaZlecen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_zlecenia')->constrained('Zlecenia')->onDelete('cascade');
            $table->string('sciezka');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ZdjeciaZlecen');
    }
};
