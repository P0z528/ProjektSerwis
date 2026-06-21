<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Hashuje istniejące hasła użytkowników, które są jeszcze zapisane jawnym tekstem.
     * Operacja jest idempotentna - hasła już zahashowane (bcrypt/argon) są pomijane.
     */
    public function up(): void
    {
        $uzytkownicy = DB::table('Uzytkownicy')->get();

        foreach ($uzytkownicy as $u) {
            $haslo = (string) $u->haslo;

            // Pomijamy hasła, które są już poprawnym hashem.
            $info = password_get_info($haslo);
            if (($info['algo'] ?? 0) !== 0) {
                continue;
            }

            DB::table('Uzytkownicy')
                ->where('id', $u->id)
                ->update(['haslo' => Hash::make($haslo)]);
        }
    }

    public function down(): void
    {
        // Brak możliwości odwrócenia hashowania (hasła są jednokierunkowe).
    }
};
