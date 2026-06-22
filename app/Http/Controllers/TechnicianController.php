<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TechnicianController
{
    private const AKTYWNE_STATUSY = [
        'Poprawka (Priorytet)',
        'W naprawie',
        'Czeka na części',
        'Części dostępne',
    ];

    private const LIMIT_AKTYWNYCH_ZLECEN = 4;

    /**
     * Czy zlecenie ma zapotrzebowania jeszcze niewydane z magazynu (Oczekuje, Do zamówienia itd.).
     */
    private function czyMaNiezrealizowaneZapotrzebowania(int $idZlecenia): bool
    {
        return DB::table('Zapotrzebowania')
            ->where('id_zlecenia', $idZlecenia)
            ->where('status', '!=', 'Wydano')
            ->exists();
    }

    private function policzAktywneZlecenia(int $technikId): int
    {
        return DB::table('Zlecenia')
            ->where('id_technika', $technikId)
            ->whereIn('status', self::AKTYWNE_STATUSY)
            ->count();
    }

    public function index() {
        $userId = Auth::id();

        // 1. Zlecenia w puli wspólnej (bez technika, priorytet na górze, najstarsze pierwsze)
        $pula = DB::table('Zlecenia as Z')
            ->leftJoin('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->whereNull('Z.id_technika')
            ->whereIn('Z.status', ['W kolejce', 'Przyjęte'])
            ->select(
                'Z.id',
                'U.model',
                'Z.status',
                'Z.opis_usterki',
                'Z.zdjecie',
                'Z.powod_odrzucenia',
                'Z.klient_odrzucil_koszty',
                'Z.created_at'
            )
            ->orderByRaw("CASE WHEN (Z.powod_odrzucenia IS NOT NULL AND Z.powod_odrzucenia != '') OR Z.klient_odrzucil_koszty = 1 THEN 0 ELSE 1 END")
            ->orderBy('Z.created_at', 'asc')
            ->orderBy('Z.id', 'asc')
            ->get();

        // 2. Zlecenia przypisane do technika
        $moje = DB::table('Zlecenia as Z')
            ->leftJoin('Urzadzenia as U', 'Z.id_urzadzenia', '=', 'U.id')
            ->where('Z.id_technika', $userId)
            ->whereIn('Z.status', self::AKTYWNE_STATUSY)
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

        $niezrealizowaneZap = DB::table('Zapotrzebowania')
            ->whereIn('id_zlecenia', $moje->pluck('id'))
            ->where('status', '!=', 'Wydano')
            ->pluck('id_zlecenia')
            ->unique()
            ->flip();

        // Eager loading: wszystkie części/usługi zgłoszone do zlecenia (również dodatkowe,
        // dołożone przez technika poza pierwotną diagnozą) wraz z ich statusem wydania.
        $czesciZlecen = DB::table('Zapotrzebowania as z')
            ->join('CzesciKatalog as ck', 'z.id_czesci_katalog', '=', 'ck.id')
            ->whereIn('z.id_zlecenia', $moje->pluck('id'))
            ->select('z.id_zlecenia', 'z.status as zap_status', 'z.dodatkowa', 'ck.nazwa_czesci', 'ck.typ', 'ck.cena')
            ->orderByDesc('z.dodatkowa')
            ->orderBy('ck.typ')
            ->orderBy('ck.nazwa_czesci')
            ->get()
            ->groupBy('id_zlecenia');

        foreach ($moje as $zl) {
            $zl->ma_niezrealizowane_zap = isset($niezrealizowaneZap[$zl->id]);
            $zl->czesci = $czesciZlecen->get($zl->id, collect());
        }

        // 3. KPI (Statystyki na górze)
        $kpiDoPodjecia = $pula->count();
        $kpiAktywne = $moje->count();
        $kpiBrakCzesci = DB::table('Zlecenia')->where('id_technika', $userId)->where('status', 'Czeka na części')->count();

        $limitAktywnych = self::LIMIT_AKTYWNYCH_ZLECEN;
        $limitOsiagniety = $kpiAktywne >= $limitAktywnych;

        return view('technik.index', compact(
            'pula', 'moje', 'kpiDoPodjecia', 'kpiAktywne', 'kpiBrakCzesci',
            'limitAktywnych', 'limitOsiagniety'
        ));
    }

    public function takeOrder($id) {
        if ($this->policzAktywneZlecenia((int) Auth::id()) >= self::LIMIT_AKTYWNYCH_ZLECEN) {
            return back()->with('error', 'Osiągnąłeś limit 4 aktywnych zleceń. Zakończ coś, aby wziąć kolejne.');
        }

        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();
        if (!$zlecenie || !in_array($zlecenie->status, ['W kolejce', 'Przyjęte'], true) || $zlecenie->id_technika !== null) {
            return back()->with('error', 'To zlecenie nie jest już dostępne w puli.');
        }

        $oczekujeNaCzesci = $this->czyMaNiezrealizowaneZapotrzebowania((int) $id);

        DB::table('Zlecenia')->where('id', $id)->update([
            'id_technika' => Auth::id(),
            'status' => $oczekujeNaCzesci ? 'Czeka na części' : 'W naprawie',
        ]);

        if ($oczekujeNaCzesci) {
            return back()->with('warning', 'Zlecenie przypisane. Oczekujesz na wydanie części z magazynu');
        }

        return back()->with('success', 'Urządzenie trafiło na Twój stół serwisowy.');
    }

    public function finishOrder($id) {
        $zlecenie = DB::table('Zlecenia')->where('id', $id)->first();

        if ($zlecenie && $zlecenie->id_technika == Auth::id()) {

            if ($zlecenie->status === 'Czeka na części' || $this->czyMaNiezrealizowaneZapotrzebowania((int) $id)) {
                return back()->with('error', 'Nie można zakończyć naprawy. Magazyn nie wydał jeszcze wszystkich zamówionych części.');
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
     * które z nich wynikają z pierwotnej diagnozy (SUGEROWANE — tylko do podświetlenia).
     * Technik ma pełną swobodę wyboru: może je odznaczyć lub dobrać inne.
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
                // Flaga wyłącznie podpowiedzi — NIE jest wymuszana przy zamawianiu.
                $c->sugerowana = in_array($c->nazwa_czesci, $pierwotne);
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

        $noweZapotrzebowania = [];
        $kosztDodatkowy = 0;

        foreach ($czesci_id as $czesc_id) {
            if (DB::table('Zapotrzebowania')
                ->where('id_zlecenia', $id)
                ->where('id_czesci_katalog', $czesc_id)
                ->exists()) {
                continue;
            }

            $dane = $czesciDane->get($czesc_id);
            $czyDodatkowa = $dane ? !in_array($dane->nazwa_czesci, $pierwotne) : false;

            if ($czyDodatkowa && $dane) {
                $kosztDodatkowy += (float) $dane->cena;
            }

            $noweZapotrzebowania[] = [
                'id_zlecenia' => $id,
                'id_czesci_katalog' => $czesc_id,
                'status' => 'Oczekuje',
                'dodatkowa' => $czyDodatkowa,
            ];
        }

        if (empty($noweZapotrzebowania)) {
            return back()->with('warning', 'Wybrane części zostały już zgłoszone do magazynu dla tego zlecenia.');
        }

        DB::transaction(function () use ($id, $noweZapotrzebowania, $kosztDodatkowy, $zlecenie) {
            DB::table('Zlecenia')->where('id', $id)->update(['status' => 'Czeka na części']);
            DB::table('Zapotrzebowania')->insert($noweZapotrzebowania);

            if ($kosztDodatkowy > 0) {
                DB::table('Zlecenia')->where('id', $id)->update([
                    'koszt' => (float) $zlecenie->koszt + $kosztDodatkowy,
                ]);
            }
        });

        return back()->with('success', 'Zapotrzebowanie zgłoszone do magazynu! Naprawa wstrzymana.');
    }
}
