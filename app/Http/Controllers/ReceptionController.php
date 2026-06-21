<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceptionController
{
    /**
     * Normalizuje numer kierunkowy: puste -> +48, same cyfry -> dodaje "+".
     */
    private function normalizujKierunkowy(?string $wartosc): string
    {
        $kierunkowy = str_replace(' ', '', trim((string) $wartosc));

        if ($kierunkowy === '') {
            return '+48';
        }

        if (preg_match('/^[0-9]+$/', $kierunkowy)) {
            return '+' . $kierunkowy;
        }

        if (!str_starts_with($kierunkowy, '+')) {
            $kierunkowy = '+' . ltrim($kierunkowy, '+');
        }

        return $kierunkowy;
    }

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
        $kierunkowy = $this->normalizujKierunkowy($request->input('kierunkowy'));
        $request->merge(['kierunkowy' => $kierunkowy]);
        $czyPolski = in_array($kierunkowy, ['+48'], true);

        $regulaTelefonu = $czyPolski
            ? ['required', 'regex:/^[0-9]{9}$/']
            : ['required', 'regex:/^[0-9]{1,15}$/'];

        $validated = $request->validate([
            'imie' => 'required|string|max:26',
            'nazwisko' => 'required|string|max:26',
            'kierunkowy' => ['required', 'string', 'max:4', 'regex:/^\+[0-9]{1,3}$/'],
            'telefon' => $regulaTelefonu,
            'typ' => 'required',
            'model' => 'required',
            'numer_seryjny' => 'required|string|max:26',
            'data_naprawy' => 'required|date|after_or_equal:today',
            'czesci' => 'required|array',
            'zdjecia' => 'nullable|array',
            'zdjecia.*' => 'image|mimes:jpeg,png,jpg,webp|max:4096'
        ], [
            'kierunkowy.regex' => 'Podaj poprawny numer kierunkowy (np. +48).',
            'kierunkowy.max' => 'Numer kierunkowy może mieć maksymalnie 4 znaki.',
            'telefon.regex' => $czyPolski
                ? 'Polski numer telefonu musi mieć dokładnie 9 cyfr.'
                : 'Numer telefonu może mieć maksymalnie 15 cyfr (tylko cyfry).',
        ], [
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

        // Dane wybranych części/usług (potrzebne też do wydruku) - liczone raz, przed transakcją
        $czesciDane = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $validated['model'])
            ->whereIn('ck.nazwa_czesci', $validated['czesci'])
            ->select('ck.nazwa_czesci', 'ck.cena')
            ->get();

        $koszt = (float) $czesciDane->sum('cena');
        $telefonPelny = trim($validated['kierunkowy'] . ' ' . $validated['telefon']);

        // Przypisujemy wynik całej transakcji do zmiennej $noweZlecenieId
        $noweZlecenieId = DB::transaction(function() use ($validated, $sciezkiZdjec, $koszt, $telefonPelny) {
            $kid = DB::table('Klienci')->insertGetId([
                'imie' => $validated['imie'],
                'nazwisko' => $validated['nazwisko'],
                'telefon' => $telefonPelny
            ]);

            $uid = DB::table('Urzadzenia')->insertGetId([
                'id_klienta' => $kid,
                'numer_seryjny' => $validated['numer_seryjny'],
                'model' => $validated['model']
            ]);

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

        // Generujemy i zapisujemy plik wydruku zlecenia na serwerze
        $this->zapiszWydrukZlecenia($noweZlecenieId, [
            'imie' => $validated['imie'],
            'nazwisko' => $validated['nazwisko'],
            'telefon' => $telefonPelny,
            'typ' => $validated['typ'],
            'model' => $validated['model'],
            'numer_seryjny' => $validated['numer_seryjny'],
            'data_naprawy' => $validated['data_naprawy'],
            'czesci' => $czesciDane,
            'koszt' => $koszt,
        ]);

        // Komunikat sukcesu + dane do modala potwierdzenia z przyciskiem wydruku
        return back()
            ->with('success', 'Zlecenie przyjęte pomyślnie! Numer zlecenia dla klienta: #' . $noweZlecenieId)
            ->with('nowe_zlecenie', [
                'id' => $noweZlecenieId,
                'numer_seryjny' => $validated['numer_seryjny'],
            ]);
    }

    /**
     * Buduje treść szablonu zlecenia i zapisuje go jako plik .txt
     * w storage/app/public/wydruki/Zlecenie_{ID}.txt
     */
    private function zapiszWydrukZlecenia(int $id, array $dane): void
    {
        $linie = [];
        $linie[] = '====================================';
        $linie[] = '        ELECTROSERVICE - ZLECENIE';
        $linie[] = '====================================';
        $linie[] = 'Numer zlecenia : #' . $id;
        $linie[] = 'Data przyjęcia : ' . now()->format('Y-m-d H:i');
        $linie[] = 'Termin naprawy : ' . $dane['data_naprawy'];
        $linie[] = '------------------------------------';
        $linie[] = 'KLIENT';
        $linie[] = '  Imię i nazwisko : ' . $dane['imie'] . ' ' . $dane['nazwisko'];
        $linie[] = '  Telefon         : ' . $dane['telefon'];
        $linie[] = '------------------------------------';
        $linie[] = 'URZĄDZENIE';
        $linie[] = '  Typ          : ' . $dane['typ'];
        $linie[] = '  Model        : ' . $dane['model'];
        $linie[] = '  Numer seryjny: ' . $dane['numer_seryjny'];
        $linie[] = '------------------------------------';
        $linie[] = 'USŁUGI / CZĘŚCI';
        foreach ($dane['czesci'] as $czesc) {
            $linie[] = '  - ' . $czesc->nazwa_czesci . ' : ' . number_format((float) $czesc->cena, 2) . ' PLN';
        }
        $linie[] = '------------------------------------';
        $linie[] = 'KOSZT CAŁKOWITY : ' . number_format($dane['koszt'], 2) . ' PLN';
        $linie[] = '====================================';
        $linie[] = 'Dziękujemy za skorzystanie z naszych usług.';

        $tresc = implode("\r\n", $linie);

        Storage::disk('public')->put('wydruki/Zlecenie_' . $id . '.txt', $tresc);
    }

    /**
     * Zwraca zapisany plik wydruku zlecenia do pobrania.
     */
    public function downloadWydruk($id)
    {
        $sciezka = 'wydruki/Zlecenie_' . (int) $id . '.txt';

        if (!Storage::disk('public')->exists($sciezka)) {
            return back()->with('error', 'Nie znaleziono pliku wydruku dla tego zlecenia.');
        }

        return response()->download(
            Storage::disk('public')->path($sciezka),
            'Zlecenie_' . (int) $id . '.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
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

        // Walidacja duplikatów odporna na wielkość liter i polskie znaki diakrytyczne.
        // Porównujemy znormalizowane (mb_strtolower) nazwy w PHP, bo SQLite nie obsługuje
        // natywnie case-insensitive dla znaków Unicode (np. "Słuchawki" == "SŁUCHAWKI").
        $szukana = mb_strtolower(trim($request->nazwa_czesci), 'UTF-8');

        $istniejaca = DB::table('CzesciKatalog')
            ->where('id_modelu', $model->id)
            ->get()
            ->first(fn ($poz) => mb_strtolower(trim($poz->nazwa_czesci), 'UTF-8') === $szukana);

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

    // API: pełna lista pozycji cennika dla modelu (z ID - do edycji/usuwania)
    public function getCatalogByModel($model) {
        $pozycje = DB::table('CzesciKatalog as ck')
            ->join('ModeleApple as m', 'ck.id_modelu', '=', 'm.id')
            ->where('m.model', $model)
            ->orderBy('ck.typ')
            ->orderBy('ck.nazwa_czesci')
            ->select('ck.id', 'ck.nazwa_czesci', 'ck.cena', 'ck.typ')
            ->get();
        return response()->json($pozycje);
    }

    // Edycja ceny (i typu) istniejącej pozycji cennika
    public function updatePart(Request $request, $id) {
        $request->validate([
            'cena' => 'required|numeric|min:0',
            'typ_pozycji' => 'required|in:Część,Usługa',
        ]);

        $pozycja = DB::table('CzesciKatalog')->where('id', $id)->first();
        if (!$pozycja) {
            return back()->with('error', 'Nie znaleziono pozycji cennika.');
        }

        DB::table('CzesciKatalog')->where('id', $id)->update([
            'cena' => $request->cena,
            'typ' => $request->typ_pozycji,
        ]);

        return back()->with('success', 'Zaktualizowano pozycję cennika.');
    }

    // Usunięcie pozycji cennika
    public function deletePart($id) {
        $pozycja = DB::table('CzesciKatalog')->where('id', $id)->first();
        if (!$pozycja) {
            return back()->with('error', 'Nie znaleziono pozycji cennika.');
        }

        DB::transaction(function () use ($id) {
            // Usuwamy też powiązane stany magazynowe i zapotrzebowania, by nie zostały "sieroty"
            DB::table('Zapotrzebowania')->where('id_czesci_katalog', $id)->delete();
            DB::table('Czesci')->where('id_czesci_katalog', $id)->delete();
            DB::table('CzesciKatalog')->where('id', $id)->delete();
        });

        return back()->with('success', 'Usunięto pozycję z cennika.');
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
        $numerSeryjny = trim((string) $request->query('numer_seryjny'));

        if (!$nrZlecenia || $numerSeryjny === '') {
            return response()->json(['success' => false, 'message' => 'Podaj numer zlecenia oraz numer seryjny.']);
        }

        // Szukamy zlecenia po jego numerze (ID) oraz numerze seryjnym urządzenia.
        // Numer seryjny porównujemy bez względu na wielkość liter.
        $zlecenie = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->join('Klienci as K', 'U.id_klienta', '=', 'K.id')
            ->where('Z.id', $nrZlecenia)
            ->whereRaw('LOWER(U.numer_seryjny) = ?', [mb_strtolower($numerSeryjny, 'UTF-8')])
            ->select('Z.id as id_zlecenia', 'K.imie', 'K.nazwisko', 'U.model', 'U.numer_seryjny', 'Z.status', 'Z.koszt')
            ->first();

        if ($zlecenie) {
            // Zwracamy wewnątrz tablicy "data", by zachować kompatybilność ze skryptem na froncie
            return response()->json(['success' => true, 'data' => [$zlecenie]]);
        }

        return response()->json(['success' => false, 'message' => 'Brak wyników. Sprawdź numer zlecenia oraz numer seryjny.']);
    }
}
