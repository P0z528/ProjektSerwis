<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController
{
    public function index() {
        // Pobranie unikalnych typów urządzeń
        $typy = DB::table('ModeleApple')->distinct()->pluck('typ');

        // Pobranie urządzeń o statusie 'Gotowe' do wydania
        $gotoweZlecenia = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->join('Klienci as K', 'U.id_klienta', '=', 'K.id')
            ->where('Z.status', 'Gotowe')
            ->select('Z.id', 'K.imie', 'K.nazwisko', 'U.model', 'Z.koszt', 'Z.koszt_pierwotny')
            ->get();

        // Lista klientów do panelu zarządzania (edycja danych) - opcjonalnie dla recepcji
        return view('recepcja.index', compact('typy', 'gotoweZlecenia'));
    }

    // API Endpoint: Zwraca modele dla danego typu urządzeń
    public function getModelsByType($typ) {
        $modele = DB::table('ModeleApple')->where('typ', $typ)->orderBy('model')->pluck('model');
        return response()->json($modele);
    }

    // API Endpoint: Zwraca powiązane usługi/części dla modelu
    public function getPartsByModel($model) {
        $czesci = DB::table('CzesciKatalog as c')
            ->join('ModeleApple as m', 'c.id_modelu', '=', 'm.id')
            ->where('m.model', $model)
            ->select('c.nazwa_czesci', 'c.cena')
            ->get();
        return response()->json($czesci);
    }

    // Dodanie nowego zlecenia
    public function storeOrder(Request $request) {
        $validated = $request->validate([
            'imie' => 'required|string',
            'nazwisko' => 'required|string',
            'kierunkowy' => 'required|string|max:6',
            'telefon' => 'required|digits:9',
            'typ' => 'required',
            'model' => 'required',
            'numer_seryjny' => 'required',
            'data_naprawy' => 'required|date|after_or_equal:today',
            'czesci' => 'required|array',
            'zdjecia' => 'nullable|array',
            'zdjecia.*' => 'image|mimes:jpeg,png,jpg,webp|max:4096'
        ], [], [
            'data_naprawy' => 'termin naprawy'
        ]);

        // Walidacja terminu wg reguł biznesowych (niedziela / limity miejsc)
        $dzien = \Carbon\Carbon::parse($validated['data_naprawy']);
        if ($dzien->isSunday()) {
            return back()->withErrors(['data_naprawy' => 'W niedzielę serwis jest nieczynny. Wybierz inny dzień.'])->withInput();
        }
        if (!\App\Http\Controllers\KalendarzController::czyDostepny($validated['data_naprawy'])) {
            return back()->withErrors(['data_naprawy' => 'Brak wolnych miejsc w wybranym dniu. Wybierz inny termin.'])->withInput();
        }

        // Obsługa uploadu wielu zdjęć
        $sciezkiZdjec = [];
        if ($request->hasFile('zdjecia')) {
            foreach ($request->file('zdjecia') as $plik) {
                $sciezkiZdjec[] = $plik->store('zdjecia_napraw', 'public');
            }
        }

        // Przypisujemy wynik całej transakcji do zmiennej $noweZlecenieId
        $noweZlecenieId = DB::transaction(function() use ($validated, $sciezkiZdjec) {
            $kid = DB::table('Klienci')->insertGetId([
                'imie' => $validated['imie'],
                'nazwisko' => $validated['nazwisko'],
                'telefon' => trim($validated['kierunkowy'] . ' ' . $validated['telefon'])
            ]);

            $uid = DB::table('Urzadzenia')->insertGetId([
                'id_klienta' => $kid,
                'numer_seryjny' => $validated['numer_seryjny'],
                'model' => $validated['model']
            ]);

            $czesciDane = DB::table('CzesciKatalog as ck')
                ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
                ->where('m.model', $validated['model'])
                ->whereIn('ck.nazwa_czesci', $validated['czesci'])
                ->get();

            $koszt = $czesciDane->sum('cena');
            $opis = "Wymiana/Usługa: " . implode(', ', $validated['czesci']);

            $zlecenieId = DB::table('Zlecenia')->insertGetId([
                'id_urzadzenia' => $uid,
                'status' => 'W kolejce',
                'opis_usterki' => $opis,
                'koszt' => $koszt,
                'koszt_pierwotny' => $koszt,
                'data_naprawy' => $validated['data_naprawy'],
                // Zachowujemy pierwsze zdjęcie w starej kolumnie dla zgodności wstecznej
                'zdjecie' => $sciezkiZdjec[0] ?? null
            ]);

            // Zapis wszystkich zdjęć do dedykowanej tabeli
            foreach ($sciezkiZdjec as $sciezka) {
                DB::table('ZdjeciaZlecen')->insert([
                    'id_zlecenia' => $zlecenieId,
                    'sciezka' => $sciezka
                ]);
            }

            return $zlecenieId;
        });

        // Wyrzucamy na ekran zielony komunikat z nowym numerem zlecenia
        return back()->with('success', 'Zlecenie przyjęte pomyślnie! Numer zlecenia dla klienta: #' . $noweZlecenieId);
    }

    public function storeModel(Request $request) {
        $typ = $request->input('nowy_typ') === 'Inny' ? $request->input('nowy_typ_manual') : $request->input('nowy_typ');

        DB::table('ModeleApple')->insert([
            'typ' => $typ,
            'model' => $request->input('model')
        ]);

        return back()->with('success', 'Dodano nowy model do katalogu!');
    }

    public function releaseDevice($id) {
        DB::table('Zlecenia')->where('id', $id)->update(['status' => 'Wydane']);
        return back()->with('success', 'Sprzęt został wydany klientowi!');
    }

    // Klient odrzucił podwyższony koszt -> zlecenie wraca priorytetowo do tego samego technika
    public function rejectCost($id) {
        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();
        if (!$zlecenie) {
            return back()->with('error', 'Nie znaleziono zlecenia.');
        }

        DB::table('Zlecenia')->where('id', $id)->update([
            'status' => 'Poprawka (Priorytet)',
            'klient_odrzucil_koszty' => true,
            // Przywracamy pierwotny koszt - klient płaci tyle, ile pierwotnie ustalono
            'koszt' => $zlecenie->koszt_pierwotny ?? $zlecenie->koszt,
        ]);

        return back()->with('warning', 'Klient odrzucił nowy koszt. Zlecenie wróciło priorytetowo do technika w celu wymontowania dodatkowej części.');
    }

    public function storePart(Request $request) {
        $request->validate([
            'katalog_typ' => 'required',
            'katalog_model' => 'required',
            'nazwa_czesci' => 'required|string',
            'cena' => 'required|numeric|min:0',
            'typ_pozycji' => 'required|in:Część,Usługa'
        ]);

        // Znajdź ID wybranego modelu
        $model = DB::table('ModeleApple')
            ->where('typ', $request->katalog_typ)
            ->where('model', $request->katalog_model)
            ->first();

        if (!$model) return back()->with('error', 'Wybrany model nie istnieje.');

        // Sprawdź czy taka część/usługa już istnieje dla tego modelu
        $istniejaca = DB::table('CzesciKatalog')
            ->where('id_modelu', $model->id)
            ->where('nazwa_czesci', $request->nazwa_czesci)
            ->first();

        if ($istniejaca) {
            // Jeśli istnieje, ale użytkownik jeszcze nie potwierdził nadpisania
            if (!$request->has('force_overwrite')) {
                return back()
                    ->with('ask_overwrite', 'Pozycja o nazwie "' . $request->nazwa_czesci . '" już istnieje dla tego modelu. Czy chcesz zaktualizować jej cenę oraz typ?')
                    ->withInput(); // Zachowuje wpisane dane
            } else {
                // Użytkownik potwierdził -> aktualizujemy (nadpisujemy)
                DB::table('CzesciKatalog')->where('id', $istniejaca->id)->update([
                    'cena' => $request->cena,
                    'typ' => $request->typ_pozycji
                ]);
                return back()->with('success', 'Zaktualizowano istniejącą pozycję w cenniku!');
            }
        }

        // Jeśli część nie istnieje, tworzymy nową
        DB::table('CzesciKatalog')->insert([
            'id_modelu' => $model->id,
            'nazwa_czesci' => $request->nazwa_czesci,
            'cena' => $request->cena,
            'typ' => $request->typ_pozycji
        ]);

        return back()->with('success', 'Dodano nową pozycję do katalogu!');
    }

    public function storeType(Request $request) {
        $request->validate([
            'nazwa_typu' => 'required|string|max:255'
        ]);

        $nazwaTypu = trim($request->nazwa_typu);

        // Zabezpieczenie: Sprawdzenie czy taki typ już istnieje w bazie (wielkość liter nie ma znaczenia)
        $istnieje = DB::table('ModeleApple')
            ->where('typ', 'LIKE', $nazwaTypu)
            ->exists();

        if ($istnieje) {
            return back()->with('error', 'Typ urządzenia "' . $nazwaTypu . '" już istnieje w katalogu!');
        }

        // Zapisujemy nowy typ z unikalną nazwą techniczną dla modelu, aby zachować unikalność pola w bazie
        DB::table('ModeleApple')->insert([
            'typ' => $nazwaTypu,
            'model' => 'Brak modeli (' . $nazwaTypu . ')'
        ]);

        return back()->with('success', 'Pomyślnie dodano nowy typ urządzenia do systemu!');
    }

    public function checkClientStatus(Request $request) {
        $imie = $request->query('imie');
        $nazwisko = $request->query('nazwisko');

        if (!$imie || !$nazwisko) {
            return response()->json(['success' => false, 'message' => 'Podaj imię i nazwisko klienta.']);
        }

        $urzadzenia = DB::table('Urzadzenia as U')
            ->join('Zlecenia as Z', 'U.id', '=', 'Z.id_urzadzenia')
            ->join('Klienci as K', 'U.id_klienta', '=', 'K.id')
            ->where('K.imie', 'LIKE', $imie)
            ->where('K.nazwisko', 'LIKE', $nazwisko)
            ->select('Z.id as id_zlecenia', 'U.model', 'U.numer_seryjny', 'Z.status', 'Z.koszt')
            ->orderBy('Z.id', 'desc')
            ->get();

        if ($urzadzenia->isNotEmpty()) {
            return response()->json(['success' => true, 'data' => $urzadzenia]);
        }

        return response()->json(['success' => false, 'message' => 'Nie znaleziono urządzeń dla podanego klienta.']);
    }

    public function checkOrderStatus(Request $request) {
        $nrZlecenia = $request->query('zlecenie');
        $telefon = $request->query('telefon');

        if (!$nrZlecenia || !$telefon) {
            return response()->json(['success' => false, 'message' => 'Podaj numer zlecenia i numer telefonu.']);
        }

        // Szukamy konkretnego zlecenia i sprawdzamy, czy telefon klienta pasuje
        $zlecenie = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->join('Klienci as K', 'U.id_klienta', '=', 'K.id')
            ->where('Z.id', $nrZlecenia)
            ->where('K.telefon', 'LIKE', '%' . trim($telefon) . '%') // Zabezpieczenie na wypadek prefiksów np. +48
            ->select('Z.id as id_zlecenia', 'U.model', 'U.numer_seryjny', 'Z.status', 'Z.koszt')
            ->first();

        if ($zlecenie) {
            // Zwracamy wewnątrz tablicy "data", by zachować kompatybilność ze skryptem na froncie
            return response()->json(['success' => true, 'data' => [$zlecenie]]);
        }

        return response()->json(['success' => false, 'message' => 'Brak wyników. Sprawdź numer zlecenia i podany telefon.']);
    }
}
