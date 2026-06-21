<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Zlecenia', function (Blueprint $table) {
            $table->timestamps();
        });

        $ids = DB::table('Zlecenia')->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $baza = now()->subMinutes($ids->count());
        foreach ($ids as $i => $id) {
            $czas = $baza->copy()->addMinutes($i);
            DB::table('Zlecenia')->where('id', $id)->update([
                'created_at' => $czas,
                'updated_at' => $czas,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('Zlecenia', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
