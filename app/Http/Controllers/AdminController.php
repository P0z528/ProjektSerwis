<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController
{
    // Role, których system wymaga w minimum 1 egzemplarzu
    private const WYMAGANE_ROLE = ['Admin', 'Technik', 'Magazyn', 'Recepcja'];

    /**
     * Sprawdza, czy dany pracownik jest OSTATNIM przedstawicielem swojej roli.
     */
    private function czyOstatniWRoli(string $rola): bool
    {
        return DB::table('Uzytkownicy')->where('rola', $rola)->count() <= 1;
    }

    public function index() {
        // 1. Karty KPI
        $aktywne = DB::table('Zlecenia')->where('status', '!=', 'Wydane')->count();
        $wNaprawie = DB::table('Zlecenia')->where('status', 'W naprawie')->count();
        $doWydania = DB::table('Zlecenia')->whereIn('status', ['Gotowe', 'Do wydania'])->count();
        $przychod = DB::table('Zlecenia')->where('status', 'LIKE', '%Wydane%')->sum('koszt');

        // 2. Dane do Wykresu Donut (Statusy)
        $statusy = DB::table('Zlecenia')
            ->select('status', DB::raw('count(*) as total'))
            ->where('status', '!=', 'Wydane')
            ->groupBy('status')
            ->get();

        $donutLabels = $statusy->pluck('status');
        $donutData = $statusy->pluck('total');

        // 3. Dane do Wykresu Bar (Top 4 modele w serwisie)
        $wszystkieModele = DB::table('Zlecenia')
            ->join('Urzadzenia', 'Zlecenia.id_urzadzenia', '=', 'Urzadzenia.id')
            ->where('Zlecenia.status', '!=', 'Wydane')
            ->pluck('Urzadzenia.model'); // Pobieramy tylko płaską listę nazw modeli

        // Używamy kolekcji Laravela, aby pogrupować sprzęt po pierwszym słowie (np. iPhone, MacBook), zliczyć go i posortować
        $pogrupowane = $wszystkieModele->countBy(function ($modelName) {
            return explode(' ', $modelName)[0];
        })->sortByDesc(function ($count) {
            return $count;
        })->take(4);

        $barLabels = $pogrupowane->keys();
        $barData = $pogrupowane->values();

        // 4. Tabela "Kontrola jakości"
        $doKontroli = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->leftJoin('Uzytkownicy as T', 'Z.id_technika', '=', 'T.id')
            ->select('Z.id', 'U.model', 'T.login as technik', 'Z.opis_usterki', 'Z.koszt')
            ->where('Z.status', 'Do kontroli')
            ->get();

        // 5. Lista klientów do edycji - TYLKO ci z przynajmniej jednym AKTYWNYM zleceniem
        //    (zlecenie inne niż 'Wydane' / 'Gotowe'). Klienci bez zleceń lub tylko z historią są pomijani.
        $klienci = DB::table('Klienci as K')
            ->join('Urzadzenia as U', 'U.id_klienta', '=', 'K.id')
            ->join('Zlecenia as Z', 'Z.id_urzadzenia', '=', 'U.id')
            ->whereNotIn('Z.status', ['Wydane', 'Gotowe'])
            ->select('K.id', 'K.imie', 'K.nazwisko', 'K.telefon')
            ->distinct()
            ->orderBy('K.nazwisko')
            ->orderBy('K.imie')
            ->get();

        // 6. Lista pracowników (zarządzanie kontami) + flaga "ostatni w roli"
        $liczbaWRoli = DB::table('Uzytkownicy')
            ->select('rola', DB::raw('COUNT(*) as ile'))
            ->groupBy('rola')
            ->pluck('ile', 'rola');

        $pracownicy = DB::table('Uzytkownicy')->orderBy('rola')->orderBy('login')->get();
        foreach ($pracownicy as $p) {
            $p->ostatni_w_roli = ((int) ($liczbaWRoli[$p->rola] ?? 0)) <= 1;
        }

        return view('admin.index', compact(
            'aktywne', 'wNaprawie', 'doWydania', 'przychod',
            'donutLabels', 'donutData', 'barLabels', 'barData',
            'doKontroli', 'klienci', 'pracownicy'
        ));
    }

    public function approve($id) {
        DB::table('Zlecenia')->where('id', $id)->update(['status' => 'Gotowe']);
        return back()->with('success', 'Sprzęt zatwierdzony. Gotowy do wydania.');
    }

    public function reject(Request $request, $id) {
        $request->validate([
            'powod_odrzucenia' => 'required|string|max:1000',
        ], [
            'powod_odrzucenia.required' => 'Podaj powód odrzucenia (co zostało źle wykonane).'
        ]);

        DB::table('Zlecenia')->where('id', $id)->update([
            'status' => 'Poprawka (Priorytet)',
            'powod_odrzucenia' => $request->powod_odrzucenia,
        ]);
        return back()->with('warning', 'Odrzucono! Sprzęt wraca na warsztat z priorytetem do tego samego technika.');
    }

    // --- ZARZĄDZANIE KLIENTAMI ---
    public function updateClient(Request $request, $id) {
        $request->validate([
            'imie' => 'required|string|max:255',
            'nazwisko' => 'required|string|max:255',
            'telefon' => 'nullable|string|max:30',
        ]);

        DB::table('Klienci')->where('id', $id)->update([
            'imie' => $request->imie,
            'nazwisko' => $request->nazwisko,
            'telefon' => $request->telefon,
        ]);

        return back()->with('success', 'Dane klienta zostały zaktualizowane.');
    }

    // --- ZARZĄDZANIE PRACOWNIKAMI ---
    public function storeEmployee(Request $request) {
        $request->validate([
            'login' => 'required|string|max:255|unique:Uzytkownicy,login',
            'haslo' => 'required|string|min:4|max:255',
            'rola' => 'required|in:Admin,Technik,Recepcja,Magazyn',
        ], [
            'login.unique' => 'Pracownik o takim loginie już istnieje.'
        ]);

        DB::table('Uzytkownicy')->insert([
            'login' => $request->login,
            'haslo' => $request->haslo,
            'rola' => $request->rola,
        ]);

        return back()->with('success', 'Dodano nowego pracownika: ' . $request->login);
    }

    public function updateEmployee(Request $request, $id) {
        $request->validate([
            'login' => 'required|string|max:255|unique:Uzytkownicy,login,' . $id,
            'haslo' => 'nullable|string|min:4|max:255',
            'rola' => 'required|in:Admin,Technik,Recepcja,Magazyn',
        ]);

        $pracownik = DB::table('Uzytkownicy')->where('id', $id)->first();
        if (!$pracownik) {
            return back()->with('error', 'Nie znaleziono pracownika.');
        }

        // ŻELAZNA ZASADA: nie można zmienić roli ostatniej osoby na danym stanowisku
        if ($pracownik->rola !== $request->rola && $this->czyOstatniWRoli($pracownik->rola)) {
            return back()->with('error', "Nie można zmienić roli ostatniego pracownika na stanowisku: {$pracownik->rola}. System wymaga minimum jednej osoby w tej roli.");
        }

        $dane = [
            'login' => $request->login,
            'rola' => $request->rola,
        ];
        // Hasło zmieniamy tylko jeśli zostało podane
        if ($request->filled('haslo')) {
            $dane['haslo'] = $request->haslo;
        }

        DB::table('Uzytkownicy')->where('id', $id)->update($dane);

        return back()->with('success', 'Zaktualizowano dane pracownika.');
    }

    public function deleteEmployee($id) {
        $pracownik = DB::table('Uzytkownicy')->where('id', $id)->first();
        if (!$pracownik) {
            return back()->with('error', 'Nie znaleziono pracownika.');
        }

        // ŻELAZNA ZASADA: w systemie musi pozostać min. 1 osoba w każdej roli
        if ($this->czyOstatniWRoli($pracownik->rola)) {
            return back()->with('error', "Nie można usunąć ostatniego pracownika na stanowisku: {$pracownik->rola}. System wymaga minimum jednej osoby w tej roli.");
        }

        DB::transaction(function () use ($id, $pracownik) {
            // FIX "sierocych" zleceń: jeśli usuwamy technika, jego niezakończone zlecenia
            // NIE mogą zniknąć - odpinamy je (id_technika = NULL) i przywracamy do obiegu.
            if ($pracownik->rola === 'Technik') {
                $notatka = ' [SYSTEM] Poprzedni technik został usunięty z systemu. Zlecenie wymaga przypisania nowego technika.';

                $zlecenia = DB::table('Zlecenia')
                    ->where('id_technika', $id)
                    ->whereNotIn('status', ['Wydane', 'Gotowe'])
                    ->get();

                foreach ($zlecenia as $z) {
                    // Mapowanie statusów powracających zleceń:
                    // a) "W naprawie" -> z powrotem do ogólnej puli "W kolejce"
                    // b) "Czeka na części" -> status zostaje (czekamy na dostawę)
                    // c) "Części dostępne" -> status zostaje (części już są)
                    // Pozostałe aktywne stany na warsztacie (np. "Poprawka (Priorytet)") również wracają do puli.
                    $nowyStatus = $z->status;
                    if (in_array($z->status, ['W naprawie', 'Poprawka (Priorytet)'])) {
                        $nowyStatus = 'W kolejce';
                    }

                    DB::table('Zlecenia')->where('id', $z->id)->update([
                        'id_technika' => null,
                        'status' => $nowyStatus,
                        'opis_usterki' => trim(($z->opis_usterki ?? '') . $notatka),
                    ]);
                }
            }

            DB::table('Uzytkownicy')->where('id', $id)->delete();
        });

        $info = $pracownik->rola === 'Technik'
            ? 'Technik został usunięty. Jego aktywne zlecenia wróciły do puli i wymagają przypisania nowego technika.'
            : 'Pracownik został usunięty.';

        return back()->with('success', $info);
    }
}
