<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KalendarzController
{
    // Limity miejsc na dany dzień tygodnia
    public const LIMIT_SOBOTA = 10;
    public const LIMIT_DZIEN_ROBOCZY = 13;

    /**
     * Zwraca pojemność (liczbę miejsc) dla danego dnia.
     * Niedziela = 0 (nieczynne), Sobota = 10, Pon-Pt = 13.
     */
    public static function pojemnoscDnia(Carbon $dzien): int
    {
        if ($dzien->isSunday()) {
            return 0;
        }
        if ($dzien->isSaturday()) {
            return self::LIMIT_SOBOTA;
        }
        return self::LIMIT_DZIEN_ROBOCZY;
    }

    /**
     * Liczba zajętych miejsc danego dnia (zlecenia z przypisaną datą naprawy).
     */
    public static function zajeteMiejsca(string $data): int
    {
        return DB::table('Zlecenia')->whereDate('data_naprawy', $data)->count();
    }

    /**
     * Czy dany dzień jest dostępny do rezerwacji (nie niedziela i są wolne miejsca).
     */
    public static function czyDostepny(string $data): bool
    {
        $dzien = Carbon::parse($data);
        $pojemnosc = self::pojemnoscDnia($dzien);
        if ($pojemnosc === 0) {
            return false;
        }
        return self::zajeteMiejsca($data) < $pojemnosc;
    }

    /**
     * Feed dla FullCalendar (publiczny - ekran logowania).
     * FullCalendar dołącza parametry start oraz end.
     */
    public function feed(Request $request)
    {
        $start = $request->query('start') ? Carbon::parse($request->query('start')) : Carbon::today();
        $end = $request->query('end') ? Carbon::parse($request->query('end')) : Carbon::today()->addMonth();

        // Pobieramy zajętość raz, zgrupowaną po dacie
        $zajetosc = DB::table('Zlecenia')
            ->whereNotNull('data_naprawy')
            ->whereBetween('data_naprawy', [$start->toDateString(), $end->toDateString()])
            ->select('data_naprawy', DB::raw('COUNT(*) as ile'))
            ->groupBy('data_naprawy')
            ->pluck('ile', 'data_naprawy');

        $dzisiaj = Carbon::today();

        $events = [];
        for ($dzien = $start->copy(); $dzien->lt($end); $dzien->addDay()) {
            $dataStr = $dzien->toDateString();

            // Dostępność pokazujemy wyłącznie od dzisiaj w przód.
            // Dni z przeszłości oznaczamy neutralnym szarym statusem (bez wolnych miejsc).
            if ($dzien->lt($dzisiaj)) {
                $events[] = [
                    'title' => 'Niedostępne',
                    'start' => $dataStr,
                    'color' => '#d1d5db',
                    'display' => 'block',
                ];
                continue;
            }

            $pojemnosc = self::pojemnoscDnia($dzien);

            if ($pojemnosc === 0) {
                $events[] = [
                    'title' => 'Nieczynne',
                    'start' => $dataStr,
                    'color' => '#6b7280',
                    'display' => 'block',
                ];
                continue;
            }

            $zajete = (int) ($zajetosc[$dataStr] ?? 0);
            $wolne = max(0, $pojemnosc - $zajete);

            if ($wolne === 0) {
                $events[] = ['title' => 'Brak miejsc', 'start' => $dataStr, 'color' => '#ef4444', 'display' => 'block'];
            } elseif ($wolne <= 3) {
                $events[] = ['title' => "Zostało $wolne miejsc", 'start' => $dataStr, 'color' => '#f59e0b', 'display' => 'block'];
            } else {
                $events[] = ['title' => "Wolne terminy ($wolne)", 'start' => $dataStr, 'color' => '#10b981', 'display' => 'block'];
            }
        }

        return response()->json($events);
    }

    /**
     * Lista najbliższych dostępnych terminów dla formularza recepcji.
     */
    public function dostepneTerminy()
    {
        Carbon::setLocale('pl');
        $terminy = [];
        $dzien = Carbon::today();
        $limitDni = 60; // przeszukujemy 60 dni do przodu
        $licznik = 0;

        while (count($terminy) < 30 && $licznik < $limitDni) {
            $dataStr = $dzien->toDateString();
            $pojemnosc = self::pojemnoscDnia($dzien);

            if ($pojemnosc > 0) {
                $zajete = self::zajeteMiejsca($dataStr);
                $wolne = $pojemnosc - $zajete;
                if ($wolne > 0) {
                    $terminy[] = [
                        'data' => $dataStr,
                        'wolne' => $wolne,
                        'etykieta' => $dzien->translatedFormat('l, d.m.Y') . " — wolne miejsca: $wolne",
                    ];
                }
            }

            $dzien->addDay();
            $licznik++;
        }

        return response()->json($terminy);
    }
}
