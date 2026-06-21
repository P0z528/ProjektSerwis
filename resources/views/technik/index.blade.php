@extends('layouts.app')

@section('title', 'ElectroService - Panel Technik')

@section('content')
<div class="container-fluid">
    <div class="row vh-100">
        <div class="col-md-2 bg-white border-end d-flex flex-column justify-content-between p-3">
            <div>
                <h4 class="fw-bold text-dark m-0">ElectroService</h4>
                <p class="text-muted small mb-4">Panel Technik</p>
                <div class="nav flex-column nav-pills">
                    <button class="nav-link active text-start mb-2 bg-purple text-white">Warsztat</button>
                </div>
            </div>

            <div class="border-top pt-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">TE</div>
                    <div class="ms-2">
                        <h6 class="mb-0 fw-bold small">{{ Auth::user()->login }}</h6>
                        <span class="text-muted extra-small" style="font-size: 11px;">Technik</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Wyloguj</button>
                </form>
            </div>
        </div>

        <div class="col-md-10 p-4 overflow-auto bg-light">

            @if(session('success')) <div class="alert alert-success alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('warning') }}</div> @endif

            <div class="mb-4">
                <h2>Warsztat — panel technika</h2>
                <p class="text-muted">Wspólna pula zleceń i Twoje aktywne naprawy.</p>
            </div>

            <div class="row mb-4">
                <div class="col-md-3"><div class="card shadow-sm border-0 border-top border-purple border-3"><div class="card-body">
                    <h6 class="text-muted">Do podjęcia</h6><h3 class="fw-bold">{{ $kpiDoPodjecia }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0 border-top border-primary border-3"><div class="card-body">
                    <h6 class="text-muted">Moje aktywne</h6><h3 class="fw-bold">{{ $kpiAktywne }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0 border-top border-warning border-3"><div class="card-body">
                    <h6 class="text-muted">Brak części</h6><h3 class="fw-bold">{{ $kpiBrakCzesci }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0 border-top border-success border-3"><div class="card-body">
                    <h6 class="text-muted">W QA</h6><h3 class="fw-bold">{{ $kpiWQA }}</h3>
                </div></div></div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100 p-3">
                        <h5 class="fw-bold">Wspólna pula zleceń</h5>
                        <p class="text-muted small mb-4">Zlecenia oczekujące na podjęcie przez technika.</p>

                        <div class="d-flex flex-column gap-3">
                            @forelse($pula as $zl)
                                <div class="card bg-white border shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="text-muted fw-bold me-2">#{{ $zl->id }}</span>
                                                <span class="badge bg-indigo bg-opacity-10 text-indigo rounded-pill">{{ $zl->status }}</span>
                                            </div>
                                            <form action="{{ route('technik.takeOrder', $zl->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button class="btn btn-sm text-white" style="background-color: #8b5cf6;">▶ Biorę</button>
                                            </form>
                                        </div>
                                        <h5 class="fw-bold m-0">{{ $zl->model ?? 'Nieznane urządzenie' }}</h5>
                                        <p class="text-muted small mt-1 mb-0">{{ Str::limit($zl->opis_usterki, 50) }}</p>
                                        @php
                                            $galeriaPula = (!empty($zl->zdjecia)) ? $zl->zdjecia : (($zl->zdjecie ?? null) ? [$zl->zdjecie] : []);
                                        @endphp
                                        @if(count($galeriaPula) > 0)
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                @foreach($galeriaPula as $foto)
                                                    <a href="{{ asset('storage/' . $foto) }}" target="_blank">
                                                        <img src="{{ asset('storage/' . $foto) }}" alt="Zdjęcie" class="img-thumbnail" style="width: 70px; height: 70px; object-fit: cover; cursor: zoom-in;">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted p-4 border rounded bg-light">Brak nowych zleceń w puli.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100 p-3">
                        <h5 class="fw-bold">Moje naprawy</h5>
                        <p class="text-muted small mb-4">Zlecenia, którymi się aktualnie zajmujesz.</p>

                        <div class="d-flex flex-column gap-3">
                            @forelse($moje as $zl)
                                @php
                                    // Proste kolorowanie statusów jak w Pythonie
                                    $badgeClass = 'bg-light text-dark';
                                    if(str_contains($zl->status, 'Poprawka')) $badgeClass = 'bg-danger bg-opacity-10 text-danger';
                                    if(str_contains($zl->status, 'W naprawie')) $badgeClass = 'bg-success bg-opacity-10 text-success';
                                    if(str_contains($zl->status, 'Czeka na części')) $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                                    if(str_contains($zl->status, 'Części dostępne')) $badgeClass = 'bg-pink bg-opacity-10 text-danger';
                                @endphp

                                <div class="card bg-white border shadow-sm @if($zl->klient_odrzucil_koszty) border-danger border-2 @endif">
                                    <div class="card-body">

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="text-muted fw-bold me-2">#{{ $zl->id }}</span>
                                                <span class="badge {{ $badgeClass }} rounded-pill">{{ $zl->status }}</span>
                                            </div>
                                        </div>

                                        @if($zl->klient_odrzucil_koszty)
                                            <div class="alert alert-danger d-flex align-items-center fw-bold mb-3" role="alert">
                                                <span class="fs-3 me-3">⚠️</span>
                                                <div>
                                                    <div class="fs-5">KLIENT ODRZUCIŁ KOSZTY!</div>
                                                    <div class="small fw-normal">Wymontuj nową część i zamontuj starą, a następnie odeślij sprzęt do Kontroli Jakości.</div>
                                                </div>
                                            </div>
                                        @endif

                                        @if(str_contains($zl->status, 'Poprawka') && $zl->powod_odrzucenia)
                                            <div class="alert alert-warning border-warning mb-3" role="alert">
                                                <strong>Powód odrzucenia (Kontrola jakości):</strong>
                                                <div class="mt-1">{{ $zl->powod_odrzucenia }}</div>
                                            </div>
                                        @endif

                                        <div class="d-flex justify-content-between align-items-end">
                                            <div>
                                                <h5 class="fw-bold m-0">{{ $zl->model ?? 'Nieznane urządzenie' }}</h5>
                                                <p class="text-muted small mt-1 mb-0">{{ Str::limit($zl->opis_usterki, 50) }}</p>
                                            </div>

                                            <div class="d-flex gap-2">
                                                @if(!str_contains($zl->status, 'Czeka na części') && !str_contains($zl->status, 'Części dostępne'))
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-parts" data-id="{{ $zl->id }}" data-model="{{ $zl->model }}">Brak części</button>
                                                @endif
                                                <form action="{{ route('technik.finishOrder', $zl->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button class="btn btn-sm btn-success">Gotowe</button>
                                                </form>
                                            </div>
                                        </div>
                                        @php
                                            $galeria = (!empty($zl->zdjecia)) ? $zl->zdjecia : (($zl->zdjecie ?? null) ? [$zl->zdjecie] : []);
                                        @endphp
                                        @if(count($galeria) > 0)
                                            <div class="mt-3 bg-light p-3 rounded border d-block w-100">
                                                <div class="mb-2">
                                                    <span class="d-block small fw-bold text-muted mb-0">Zdjęcia sprzętu ({{ count($galeria) }})</span>
                                                    <span class="small text-muted" style="font-size: 11px;">Kliknij zdjęcie, aby otworzyć w nowej karcie</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach($galeria as $foto)
                                                        <a href="{{ asset('storage/' . $foto) }}" target="_blank" title="Kliknij, aby powiększyć">
                                                            <img src="{{ asset('storage/' . $foto) }}" alt="Zdjęcie usterki" class="img-thumbnail shadow-sm" style="width: 120px; height: 120px; object-fit: cover; cursor: zoom-in;">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted p-4 border rounded bg-light">Brak aktywnych napraw na Twoim koncie.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="partsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Zgłoś zapotrzebowanie</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="partsForm" method="POST" action="">
          @csrf
          <div class="modal-body pb-0">
            <p class="text-muted mb-3" id="modalModelText">Model: Ładowanie...</p>
            <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                <div id="partsContainer">
                    <div class="text-center text-muted spinner-border spinner-border-sm" role="status"></div> Ładowanie katalogu...
                </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-4 pb-3 justify-content-center">
            <button type="submit" class="btn text-white fw-bold w-100" style="background-color: #f59e0b;">Zamów wybrane części</button>
          </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<style>
    /* Dodatkowe drobne kolory dla estetyki */
    .border-purple { border-color: #8b5cf6 !important; }
    .text-indigo { color: #4338ca !important; }
    .bg-indigo { background-color: #e0e7ff !important; }
    .text-pink { color: #be185d !important; }
    .bg-pink { background-color: #fce7f3 !important; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const partsModal = new bootstrap.Modal(document.getElementById('partsModal'));
    const partsForm = document.getElementById('partsForm');
    const partsContainer = document.getElementById('partsContainer');
    const modalModelText = document.getElementById('modalModelText');

    document.querySelectorAll('.btn-parts').forEach(button => {
        button.addEventListener('click', function() {
            const zlecId = this.getAttribute('data-id');
            const modelName = this.getAttribute('data-model');

            // Ustaw akcję formularza i nagłówek
            partsForm.action = `/technik/czesci/${zlecId}`;
            modalModelText.innerText = `Model: ${modelName} (Zlecenie #${zlecId})`;
            partsContainer.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div> Ładowanie...</div>';

            // Pokaż modal
            partsModal.show();

            // Pobierz części przez API (per zlecenie - z oznaczeniem części wymaganych)
            fetch(`/api/technik/czesci-dla-zlecenia/${encodeURIComponent(zlecId)}`)
                .then(res => res.json())
                .then(data => {
                    partsContainer.innerHTML = '';
                    if(data.length === 0) {
                        partsContainer.innerHTML = '<p class="text-muted text-center m-0">Brak fizycznych części w katalogu dla tego modelu.</p>';
                        return;
                    }

                    const sąWymagane = data.some(c => c.wymagana);
                    if (sąWymagane) {
                        partsContainer.innerHTML += '<p class="small text-danger fw-bold mb-2">Części wymagane w tym zleceniu są podświetlone. Pozostałe pozycje będą doliczone jako dodatkowe.</p>';
                    }

                    // Wygeneruj Checkboxy (wymagane podświetlone i domyślnie zaznaczone)
                    data.forEach(czesc => {
                        const wymagana = czesc.wymagana;
                        const wrapClass = wymagana
                            ? 'form-check mb-2 p-2 rounded border border-danger bg-danger bg-opacity-10'
                            : 'form-check mb-2 p-2';
                        const cena = parseFloat(czesc.cena).toFixed(2);
                        const tag = wymagana
                            ? '<span class="badge bg-danger ms-2">Wymagana</span>'
                            : '<span class="badge bg-secondary ms-2">Dodatkowa (+' + cena + ' PLN)</span>';
                        partsContainer.innerHTML += `
                            <div class="${wrapClass}">
                                <input class="form-check-input" type="checkbox" name="czesci[]" value="${czesc.id}" id="czesc_${czesc.id}" ${wymagana ? 'checked' : ''}>
                                <label class="form-check-label" for="czesc_${czesc.id}">${czesc.nazwa_czesci} ${tag}</label>
                            </div>
                        `;
                    });
                })
                .catch(err => {
                    partsContainer.innerHTML = '<p class="text-danger text-center m-0">Błąd podczas pobierania danych z bazy.</p>';
                });
        });
    });
});
</script>
@endsection
