<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Użytkownicy
        Schema::create('Uzytkownicy', function (Blueprint $table) {
            $table->id();
            $table->string('login')->unique();
            $table->string('haslo');
            $table->string('rola');
        });

        // 2. Klienci
        Schema::create('Klienci', function (Blueprint $table) {
            $table->id();
            $table->string('imie');
            $table->string('nazwisko');
            $table->string('telefon')->nullable();
        });

        // 3. Modele Apple
        Schema::create('ModeleApple', function (Blueprint $table) {
            $table->id();
            $table->string('typ');
            $table->string('model')->unique();
        });

        // 4. Urządzenia (Zależne od Klientów)
        Schema::create('Urzadzenia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_klienta')->constrained('Klienci')->onDelete('cascade');
            $table->string('numer_seryjny')->nullable();
            $table->string('model');
        });

        // 5. Zlecenia (Zależne od Urządzeń)
        Schema::create('Zlecenia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_urzadzenia')->constrained('Urzadzenia')->onDelete('cascade');
            $table->unsignedBigInteger('id_technika')->nullable(); // Brak twardego FK w oryginale
            $table->string('status');
            $table->text('opis_usterki')->nullable();
            $table->decimal('koszt', 10, 2)->default(0);
        });

        // 6. Części Katalog (Zależne od ModeleApple)
        Schema::create('CzesciKatalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_modelu')->constrained('ModeleApple')->onDelete('cascade');
            $table->string('nazwa_czesci');
            $table->decimal('cena', 10, 2);
            $table->string('typ')->default('Część');
        });

        // 7. Części Magazyn (Zależne od Katalogu)
        Schema::create('Czesci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_czesci_katalog')->unique()->constrained('CzesciKatalog')->onDelete('cascade');
            $table->integer('ilosc')->default(0);
        });

        // 8. Zapotrzebowania
        Schema::create('Zapotrzebowania', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_zlecenia')->nullable()->constrained('Zlecenia')->onDelete('cascade');
            $table->foreignId('id_czesci_katalog')->constrained('CzesciKatalog')->onDelete('cascade');
            $table->string('status')->default('Oczekuje');
        });
    }

    public function down(): void
    {
        // Usuwanie tabel w odwrotnej kolejności, aby nie złamać kluczy obcych
        Schema::dropIfExists('Zapotrzebowania');
        Schema::dropIfExists('Czesci');
        Schema::dropIfExists('CzesciKatalog');
        Schema::dropIfExists('Zlecenia');
        Schema::dropIfExists('Urzadzenia');
        Schema::dropIfExists('ModeleApple');
        Schema::dropIfExists('Klienci');
        Schema::dropIfExists('Uzytkownicy');
    }
};
