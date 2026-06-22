<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Zlecenia', function (Blueprint $table) {
            // Termin (data) naprawy wybrany w recepcji - obsługa kalendarza i limitów
            $table->date('data_naprawy')->nullable()->after('koszt');
            // Powód odrzucenia w kontroli jakości (co zostało źle wykonane)
            $table->text('powod_odrzucenia')->nullable()->after('data_naprawy');
            // Pierwotny koszt zlecenia - do wykrywania wzrostu kosztów przy wydawce
            $table->decimal('koszt_pierwotny', 10, 2)->nullable()->after('powod_odrzucenia');
            // Flaga: klient odrzucił podwyższony koszt -> rollback do technika
            $table->boolean('klient_odrzucil_koszty')->default(false)->after('koszt_pierwotny');
        });
    }

    public function down(): void
    {
        Schema::table('Zlecenia', function (Blueprint $table) {
            $table->dropColumn(['data_naprawy', 'powod_odrzucenia', 'koszt_pierwotny', 'klient_odrzucil_koszty']);
        });
    }
};
