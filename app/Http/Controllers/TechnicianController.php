<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TechnicianController
{
    public function index() {
        $userId = Auth::id();

        // 1. Zlecenia w puli wspólnej
        $pula = DB::table('Zlecenia as Z')
            ->leftJoin('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->whereIn('Z.status', ['W kolejce', 'Przyjęte'])
            ->select('Z.id', 'U.model', 'Z.status', 'Z.opis_usterki', 'Z.zdjecie')
            ->get();

        // 2. Zlecenia przypisane do technika
        $moje = DB::table('Zlecenia as Z')
            ->leftJoin('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->where('Z.id_technika', $userId)
            ->whereIn('Z.status', ['Poprawka (Priorytet)', 'W naprawie', 'Czeka na części', 'Części dostępne'])
            ->select('Z.id', 'U.model', 'Z.status', 'Z.opis_usterki', 'Z.zdjecie', 'Z.powod_odrzucenia', 'Z.klient_odrzucil_koszty')
            ->get();

        // Doładowanie wielu zdjęć dla wszystkich widocznych zleceń
        $idsZlecen = $pula->pluck('id')->merge($moje->pluck('id'))->unique();
        $zdjeciaWszystkie = DB::table('ZdjeciaZlecen')
            ->whereIn('id_zlecenia', $idsZlecen)
            ->get()
            ->groupBy('id_zlecenia');

        $dolaczZdjecia = function ($kolekcja) use ($zdjeciaWszystkie) {
            foreach ($kolekcja as $zl) {
                $zl->zdjecia = $zdjeciaWszystkie->get($zl->id, collect())->pluck('sciezka')->all();
            }
        };
        $dolaczZdjecia($pula);
        $dolaczZdjecia($moje);

        // 3. KPI (Statystyki na górze)
        $kpiDoPodjecia = $pula->count();
        $kpiAktywne = $moje->count();
        $kpiBrakCzesci = DB::table('Zlecenia')->where('id_technika', $userId)->where('status', 'Czeka na części')->count();
        $kpiWQA = DB::table('Zlecenia')->where('status', 'Do kontroli')->count();

        return view('technik.index', compact('pula', 'moje', 'kpiDoPodjecia', 'kpiAktywne', 'kpiBrakCzesci', 'kpiWQA'));
    }

    public function takeOrder($id) {
        $aktywneZlecenia = DB::table('Zlecenia')
            ->where('id_technika', Auth::id())
            ->whereNotIn('status', ['Do kontroli', 'Wydane', 'W kolejce'])
            ->count();

        if ($aktywneZlecenia > 0) {
            return back()->with('error', 'Odrzucono: Masz już urządzenie na stole serwisowym! Zakończ bieżącą naprawę (lub zgłoś brak części), zanim weźmiesz kolejną.');
        }

        DB::table('Zlecenia')->where('id', $id)->update([
            'id_technika' => Auth::id(),
            'status' => 'W naprawie'
        ]);

        return back()->with('success', 'Urządzenie trafiło na Twój stół serwisowy. Powodzenia!');
    }

    public function finishOrder($id) {
        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();

        if ($zlecenie && $zlecenie->id_technika == Auth::id()) {

            // --- BLOKADA 1: Nie można zakończyć zlecenia bez części ---
            if ($zlecenie->status === 'Czeka na części') {
                return back()->with('warning', 'Odrzucono: Nie możesz zakończyć naprawy, ponieważ sprzęt oczekuje na dostawę części z magazynu!');
            }

            // Wysyłka do QA - czyścimy poprzednie odrzucenie i flagę rollback klienta
            DB::table('Zlecenia')->where('id', $id)->update([
                'status' => 'Do kontroli',
                'powod_odrzucenia' => null,
                'klient_odrzucil_koszty' => false,
            ]);
            return back()->with('success', 'Gotowe! Sprzęt wysłany do kontroli jakości.');
        }

        return back()->with('warning', 'Nie możesz zakończyć tego zlecenia.');
    }

    // --- LOGIKA DO ZAMAWIANIA CZĘŚCI ---

    /**
     * Zwraca fizyczne części dla modelu danego zlecenia i oznacza,
     * które z nich były WYMAGANE w pierwotnym zleceniu (do podświetlenia).
     */
    public function getPartsForOrder($id) {
        $zlecenie = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->where('Z.id', $id)
            ->select('Z.opis_usterki', 'U.model')
            ->first();

        if (!$zlecenie) {
            return response()->json([]);
        }

        // Parsujemy pierwotnie wybrane części z opisu "Wymiana/Usługa: X, Y, Z"
        $pierwotne = [];
        if ($zlecenie->opis_usterki && str_contains($zlecenie->opis_usterki, ':')) {
            $czescOpisu = substr($zlecenie->opis_usterki, strpos($zlecenie->opis_usterki, ':') + 1);
            $pierwotne = array_map('trim', explode(',', $czescOpisu));
        }

        $czesci = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $zlecenie->model)
            ->where('ck.typ', 'Część')
            ->select('ck.id', 'ck.nazwa_czesci', 'ck.cena')
            ->get()
            ->map(function ($c) use ($pierwotne) {
                $c->wymagana = in_array($c->nazwa_czesci, $pierwotne);
                return $c;
            });

        return response()->json($czesci);
    }

    public function orderParts(Request $request, $id) {
        $czesci_id = $request->input('czesci');

        if (!$czesci_id || empty($czesci_id)) {
            return back()->with('warning', 'Zaznacz co najmniej jedną część przed wysłaniem zamówienia!');
        }

        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();

        // --- BLOKADA 2: Zapobieganie podwójnym zamówieniom ---
        if (in_array($zlecenie->status, ['Czeka na części', 'Części dostępne'])) {
            return back()->with('warning', 'Odrzucono: Części do tego zlecenia zostały już zamówione lub są gotowe do odbioru!');
        }

        // Ustalamy pierwotnie wybrane części (z opisu), aby wykryć części DODATKOWE
        $pierwotne = [];
        if ($zlecenie->opis_usterki && str_contains($zlecenie->opis_usterki, ':')) {
            $czescOpisu = substr($zlecenie->opis_usterki, strpos($zlecenie->opis_usterki, ':') + 1);
            $pierwotne = array_map('trim', explode(',', $czescOpisu));
        }

        // Pobieramy dane zamawianych części z katalogu (nazwa + cena)
        $czesciDane = DB::table('CzesciKatalog')
            ->whereIn('id', $czesci_id)
            ->select('id', 'nazwa_czesci', 'cena')
            ->get()
            ->keyBy('id');

        DB::transaction(function() use ($id, $czesci_id, $czesciDane, $pierwotne, $zlecenie) {
            DB::table('Zlecenia')->where('id', $id)->update(['status' => 'Czeka na części']);

            $kosztDodatkowy = 0;
            $zapotrzebowania = [];
            foreach ($czesci_id as $czesc_id) {
                $dane = $czesciDane->get($czesc_id);
                $czyDodatkowa = $dane ? !in_array($dane->nazwa_czesci, $pierwotne) : false;

                if ($czyDodatkowa && $dane) {
                    $kosztDodatkowy += (float) $dane->cena;
                }

                $zapotrzebowania[] = [
                    'id_zlecenia' => $id,
                    'id_czesci_katalog' => $czesc_id,
                    'status' => 'Oczekuje',
                    'dodatkowa' => $czyDodatkowa,
                ];
            }
            DB::table('Zapotrzebowania')->insert($zapotrzebowania);

            // Doliczamy koszt dodatkowych części do łącznego kosztu zlecenia
            if ($kosztDodatkowy > 0) {
                DB::table('Zlecenia')->where('id', $id)->update([
                    'koszt' => (float) $zlecenie->koszt + $kosztDodatkowy,
                ]);
            }
        });

        return back()->with('success', 'Zapotrzebowanie zgłoszone do magazynu! Naprawa wstrzymana.');
    }
}
