<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MagazynController
{
    public function index() {
        // 1. Zapotrzebowania
        $zapotrzebowania = DB::table('Zapotrzebowania as z')
            ->join('CzesciKatalog as ck', 'z.id_czesci_katalog', '=', 'ck.id')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->leftJoin('Czesci as c', 'ck.id', '=', 'c.id_czesci_katalog')
            ->where('z.status', 'Oczekuje')
            ->where('z.id_zlecenia', '>', 0)
            ->select('z.id as zap_id', 'z.id_zlecenia', 'm.model', 'ck.nazwa_czesci', 'ck.id as ck_id', DB::raw('COALESCE(c.ilosc, 0) as stan'))
            ->get();

        // 2. Lista zakupów (Zgrupowana)
        $zakupy = DB::table('Zapotrzebowania as z')
            ->join('CzesciKatalog as ck', 'z.id_czesci_katalog', '=', 'ck.id')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('z.status', 'Do zamówienia')
            ->select('m.model', 'ck.nazwa_czesci', DB::raw('COUNT(z.id) as ilosc'))
            ->groupBy('m.model', 'ck.nazwa_czesci')
            ->get();

        // 3. Stan magazynu
        $stany = DB::table('Czesci as c')
            ->join('CzesciKatalog as ck', 'c.id_czesci_katalog', '=', 'ck.id')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->select('c.id', 'm.model', 'ck.nazwa_czesci', 'c.ilosc')
            ->orderBy('m.model')
            ->orderBy('ck.nazwa_czesci')
            ->get();

        // Typy urządzeń do formularza ręcznego
        $typy = DB::table('ModeleApple')->distinct()->pluck('typ');

        // Karty statystyk
        $kpiZap = $zapotrzebowania->count();
        $kpiZakupy = $zakupy->count();
        $kpiStan = $stany->count();
        $kpiLacznie = $stany->sum('ilosc');

        return view('magazyn.index', compact('zapotrzebowania', 'zakupy', 'stany', 'typy', 'kpiZap', 'kpiZakupy', 'kpiStan', 'kpiLacznie'));
    }

    public function wydaj(Request $request) {
        $wybrane = $request->input('wybrane_zap');
        if (!$wybrane) return back()->with('warning', 'Zaznacz co najmniej jedno zapotrzebowanie do realizacji!');

        // Pobieramy wybrane zapotrzebowania (pomijając ewentualne nieistniejące pozycje)
        $zapotrzebowania = DB::table('Zapotrzebowania')->whereIn('id', $wybrane)->get();
        if ($zapotrzebowania->isEmpty()) {
            return back()->with('warning', 'Zaznaczone zapotrzebowania nie istnieją.');
        }

        // Ile sztuk danej części katalogowej chcemy łącznie wydać w tej operacji
        $wymaganeIlosci = $zapotrzebowania->groupBy('id_czesci_katalog')->map->count();

        // WALIDACJA STANU MAGAZYNOWEGO
        // Jeśli choć jednej części brakuje lub jest jej mniej niż żądana ilość, blokujemy całą operację.
        foreach ($wymaganeIlosci as $idCzesciKatalog => $potrzeba) {
            $stan = (int) (DB::table('Czesci')->where('id_czesci_katalog', $idCzesciKatalog)->value('ilosc') ?? 0);
            if ($stan < $potrzeba) {
                return back()->with('error', 'Błąd: Brak wystarczającej ilości części w magazynie, nie można wydać!');
            }
        }

        // Stan wystarczający dla wszystkich pozycji - wykonujemy wydanie.
        DB::transaction(function () use ($zapotrzebowania) {
            foreach ($zapotrzebowania as $zap) {
                $czesc = DB::table('Czesci')->where('id_czesci_katalog', $zap->id_czesci_katalog)->first();

                // Ochrony wewnątrz transakcji gdyby stan zmienił się równolegle.
                if (!$czesc || $czesc->ilosc <= 0) {
                    throw new \RuntimeException('Stan magazynowy zmienił się w trakcie wydawania.');
                }

                DB::table('Czesci')->where('id', $czesc->id)->decrement('ilosc', 1);
                DB::table('Zapotrzebowania')->where('id', $zap->id)->update(['status' => 'Wydano']);

                // Sprawdzenie czy całe zlecenie jest gotowe
                $pozostale = DB::table('Zapotrzebowania')->where('id_zlecenia', $zap->id_zlecenia)->where('status', '!=', 'Wydano')->count();
                if ($pozostale == 0) {
                    DB::table('Zlecenia')->where('id', $zap->id_zlecenia)->update(['status' => 'Części dostępne']);
                }
            }
        });

        return back()->with('success', 'Wydano zaznaczone części z magazynu.');
    }

    public function przeniesDoZamowienia(Request $request) {
        $wybrane = $request->input('wybrane_zap');
        if (!$wybrane) return back()->with('warning', 'Zaznacz zapotrzebowanie, które chcesz zamówić!');

        $zablokowane = 0;
        $przeniesione = 0;

        foreach ($wybrane as $zap_id) {
            $zap = DB::table('Zapotrzebowania')->where('id', $zap_id)->first();
            if (!$zap) continue;

            $stan = DB::table('Czesci')->where('id_czesci_katalog', $zap->id_czesci_katalog)->first();
            if ($stan && $stan->ilosc > 0) {
                $zablokowane++;
                continue;
            }

            DB::table('Zapotrzebowania')->where('id', $zap_id)->update(['status' => 'Do zamówienia']);
            $przeniesione++;
        }

        if ($zablokowane > 0 && $przeniesione == 0) {
            return back()->with('warning', 'Odrzucono! Wszystkie zaznaczone części są dostępne w magazynie. Użyj przycisku "Wydaj".');
        } elseif ($zablokowane > 0 && $przeniesione > 0) {
            return back()->with('success', "Przeniesiono $przeniesione pozycji. Pominięto $zablokowane, ponieważ są aktualnie na stanie.");
        }

        return back()->with('success', 'Przeniesiono zapotrzebowania do listy zakupów.');
    }

    public function reczneZamowienie(Request $request) {
        $request->validate([
            'model' => 'required',
            'czesc' => 'required',
            'ilosc' => 'required|integer|min:1'
        ]);

        $ck = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $request->model)
            ->where('ck.nazwa_czesci', $request->czesc)
            ->select('ck.id')
            ->first();

        if (!$ck) return back()->with('error', 'Nie znaleziono części.');

       $dane = [];
        for ($i = 0; $i < $request->ilosc; $i++) {
            $dane[] = ['id_zlecenia' => null, 'id_czesci_katalog' => $ck->id, 'status' => 'Do zamówienia'];
        }
        DB::table('Zapotrzebowania')->insert($dane);

        return back()->with('success', "Dodano do listy zakupów.");
    }

    public function ksiegujDostawe() {
        DB::transaction(function () {
            $zakupy = DB::table('Zapotrzebowania')->where('status', 'Do zamówienia')->get();

            foreach ($zakupy as $z) {
                // Dodajemy na stan
                $istnieje = DB::table('Czesci')->where('id_czesci_katalog', $z->id_czesci_katalog)->first();
                if ($istnieje) {
                    DB::table('Czesci')->where('id', $istnieje->id)->increment('ilosc', 1);
                } else {
                    DB::table('Czesci')->insert(['id_czesci_katalog' => $z->id_czesci_katalog, 'ilosc' => 1]);
                }

                // Aktualizujemy oryginalne zapotrzebowania i usuwamy te ręczne
                if ($z->id_zlecenia > 0) {
                    DB::table('Zapotrzebowania')->where('id', $z->id)->update(['status' => 'Oczekuje']);
                } else {
                    DB::table('Zapotrzebowania')->where('id', $z->id)->delete();
                }
            }
        });

        return back()->with('success', 'Dostawa zaksięgowana. Stany zostały uzupełnione.');
    }

    public function szybkieDodanieIlosci(Request $request) {
        $model = $request->input('model');
        $czesc = $request->input('czesc');
        $ilosc = $request->input('ilosc', 1);

        $ck = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $model)
            ->where('ck.nazwa_czesci', $czesc)
            ->select('ck.id')
            ->first();

        if($ck) {
            $dane = [];
            for ($i = 0; $i < $ilosc; $i++) {
                $dane[] = ['id_zlecenia' => null, 'id_czesci_katalog' => $ck->id, 'status' => 'Do zamówienia'];
            }
            DB::table('Zapotrzebowania')->insert($dane);
        }

        return back()->with('success', 'Zwiększono ilość na liście zakupów.');
    }

    // API
    public function getModele($typ) {
        return response()->json(DB::table('ModeleApple')->where('typ', $typ)->pluck('model'));
    }
    public function getCzesci($model) {
        $czesci = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $model)
            ->pluck('ck.nazwa_czesci');
        return response()->json($czesci);
    }
    
    public function checkUpdates() {
        // Sprawdza, czy wpadły nowe zapotrzebowania od techników
        $oczekujace = DB::table('Zapotrzebowania')->where('status', 'Oczekuje')->count();

        return response()->json(['count' => $oczekujace]);
    }
}
