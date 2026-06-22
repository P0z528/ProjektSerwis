<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController
{
    // Role, których system wymaga w minimum 1 egzemplarzu
    private const WYMAGANE_ROLE = ['Admin', 'Technik', 'Magazyn', 'Recepcja'];

    /** Dozwolone statusy zlecenia (edycja ręczna w panelu admina). */
    private const STATUSY_ZLECEN = [
        'W kolejce',
        'Przyjęte',
        'W naprawie',
        'Czeka na części',
        'Części dostępne',
        'Do kontroli',
        'Poprawka (Priorytet)',
        'Gotowe',
        'Wydane',
    ];

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

        // Eager loading: wszystkie części/usługi przypisane do zleceń w kontroli jakości,
        // łącznie z pozycjami dodanymi wtórnie przez technika (dodatkowa = true).
        $czesciKontroli = DB::table('Zapotrzebowania as z')
            ->join('CzesciKatalog as ck', 'z.id_czesci_katalog', '=', 'ck.id')
            ->whereIn('z.id_zlecenia', $doKontroli->pluck('id'))
            ->select('z.id_zlecenia', 'z.status as zap_status', 'z.dodatkowa', 'ck.nazwa_czesci', 'ck.typ', 'ck.cena')
            ->orderByDesc('z.dodatkowa')
            ->orderBy('ck.typ')
            ->orderBy('ck.nazwa_czesci')
            ->get()
            ->groupBy('id_zlecenia');

        foreach ($doKontroli as $zl) {
            $zl->czesci = $czesciKontroli->get($zl->id, collect());
        }

        // 5. Aktywne zlecenia klientów (widok Klienci — z urządzeniami i akcjami)
        $zleceniaKlientow = DB::table('Zlecenia as Z')
            ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->join('Klienci as K', 'U.id_klienta', '=', 'K.id')
            ->whereNotIn('Z.status', ['Wydane', 'Gotowe'])
            ->select(
                'Z.id as id_zlecenia',
                'Z.status',
                'Z.koszt',
                'U.id as id_urzadzenia',
                'U.model',
                'U.numer_seryjny',
                'K.id as id_klienta',
                'K.imie',
                'K.nazwisko',
                'K.telefon'
            )
            ->orderBy('K.nazwisko')
            ->orderBy('K.imie')
            ->orderByDesc('Z.id')
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

        $statusyZlecen = self::STATUSY_ZLECEN;

        return view('admin.index', compact(
            'aktywne', 'wNaprawie', 'doWydania', 'przychod',
            'donutLabels', 'donutData', 'barLabels', 'barData',
            'doKontroli', 'zleceniaKlientow', 'pracownicy', 'statusyZlecen'
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
            'id_zlecenia' => 'nullable|integer|exists:Zlecenia,id',
            'status' => ['nullable', Rule::in(self::STATUSY_ZLECEN)],
        ]);

        $klient = DB::table('Klienci')->where('id', $id)->first();
        if (!$klient) {
            return back()->with('error', 'Nie znaleziono klienta.');
        }

        DB::transaction(function () use ($request, $id) {
            DB::table('Klienci')->where('id', $id)->update([
                'imie' => $request->imie,
                'nazwisko' => $request->nazwisko,
                'telefon' => $request->telefon,
            ]);

            if ($request->filled('id_zlecenia') && $request->filled('status')) {
                $zlecenie = DB::table('Zlecenia as Z')
                    ->join('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
                    ->where('Z.id', $request->id_zlecenia)
                    ->where('U.id_klienta', $id)
                    ->select('Z.id')
                    ->first();

                if ($zlecenie) {
                    DB::table('Zlecenia')->where('id', $request->id_zlecenia)->update([
                        'status' => $request->status,
                    ]);
                }
            }
        });

        return back()->with('success', 'Zaktualizowano dane klienta i zlecenia.');
    }

    public function deleteOrder($id) {
        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();
        if (!$zlecenie) {
            return back()->with('error', 'Nie znaleziono zlecenia.');
        }

        DB::transaction(function () use ($zlecenie) {
            DB::table('Zapotrzebowania')->where('id_zlecenia', $zlecenie->id)->delete();
            DB::table('ZdjeciaZlecen')->where('id_zlecenia', $zlecenie->id)->delete();
            DB::table('Zlecenia')->where('id', $zlecenie->id)->delete();

            $pozostaleZlecenia = DB::table('Zlecenia')
                ->where('id_urzadzenia', $zlecenie->id_urzadzenia)
                ->count();

            if ($pozostaleZlecenia === 0) {
                DB::table('Urzadzenia')->where('id', $zlecenie->id_urzadzenia)->delete();
            }
        });

        return back()->with('success', 'Zlecenie i powiązane dane zostały usunięte.');
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
            'haslo' => Hash::make($request->haslo),
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
            return back()->with('error', 'Nie można zmienić roli ostatniego pracownika w tej roli.');
        }

        $dane = [
            'login' => $request->login,
            'rola' => $request->rola,
        ];
        if ($request->filled('haslo')) {
            $dane['haslo'] = Hash::make($request->haslo);
        }

        DB::transaction(function () use ($id, $pracownik, $dane, $request) {
            // Zmiana roli z Technika na inną — odpinamy wszystkie jego zlecenia do puli.
            if ($pracownik->rola === 'Technik' && $request->rola !== 'Technik') {
                DB::table('Zlecenia')
                    ->where('id_technika', $id)
                    ->update([
                        'id_technika' => null,
                        'status' => 'W kolejce',
                    ]);
            }

            DB::table('Uzytkownicy')->where('id', $id)->update($dane);
        });

        return back()->with('success', 'Zaktualizowano dane pracownika.');
    }

    public function deleteEmployee($id) {
        $pracownik = DB::table('Uzytkownicy')->where('id', $id)->first();
        if (!$pracownik) {
            return back()->with('error', 'Nie znaleziono pracownika.');
        }

        // ŻELAZNA ZASADA: w systemie musi pozostać min. 1 osoba w każdej roli
        if ($this->czyOstatniWRoli($pracownik->rola)) {
            return back()->with('error', 'Nie można usunąć ostatniego pracownika w tej roli.');
        }

        DB::transaction(function () use ($id, $pracownik) {
            if ($pracownik->rola === 'Technik') {
                // WSZYSTKIE zlecenia technika wracają do wspólnej puli — bez wyjątków statusu.
                DB::table('Zlecenia')
                    ->where('id_technika', $id)
                    ->update([
                        'id_technika' => null,
                        'status' => 'W kolejce',
                    ]);
            }

            DB::table('Uzytkownicy')->where('id', $id)->delete();
        });

        $info = $pracownik->rola === 'Technik'
            ? 'Technik został usunięty. Jego aktywne zlecenia wróciły do puli i wymagają przypisania nowego technika.'
            : 'Pracownik został usunięty.';

        return back()->with('success', $info);
    }
}
