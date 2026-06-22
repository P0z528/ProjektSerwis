@extends('layouts.app')

@section('title', 'ElectroService - Panel Magazyn')

@section('content')
<div class="container-fluid">
    <div class="row vh-100">

        <!-- ========================================== -->
        <!-- LEWY PANEL BOCZNY (MENU GŁÓWNE)            -->
        <!-- ========================================== -->
        <div class="col-md-2 bg-white border-end d-flex flex-column justify-content-between p-3">
            <div>
                <h4 class="fw-bold text-dark m-0">ElectroService</h4>
                <p class="text-muted small mb-4">Panel Magazynier</p>

                <!-- TUTAJ PRZENIESIONO ZAKŁADKI -->
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">

                    <button class="nav-link active text-start mb-2 fw-bold text-dark" id="zapotrzebowania-tab" data-bs-toggle="pill" data-bs-target="#zapotrzebowania" type="button" role="tab">
                        Zapotrzebowania
                        <span class="badge bg-secondary rounded-pill float-end">{{ $kpiZap ?? 0 }}</span>
                    </button>

                    <button class="nav-link text-start text-dark mb-2 fw-bold" id="zakupy-tab" data-bs-toggle="pill" data-bs-target="#zakupy" type="button" role="tab">
                        Lista zakupów
                        <span class="badge bg-secondary rounded-pill float-end">{{ $kpiZakupy ?? 0 }}</span>
                    </button>

                    <button class="nav-link text-start text-dark fw-bold" id="stan-tab" data-bs-toggle="pill" data-bs-target="#stan" type="button" role="tab">
                        Stan magazynu
                    </button>

                </div>
            </div>

            <!-- DANE UŻYTKOWNIKA -->
            <div class="border-top pt-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">MA</div>
                    <div class="ms-2">
                        <h6 class="mb-0 fw-bold small">{{ Auth::user()->login }}</h6>
                        <span class="text-muted extra-small" style="font-size: 11px;">Magazynier</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Wyloguj</button>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- PRAWA STRONA (ZAWARTOŚĆ)                   -->
        <!-- ========================================== -->
        <div class="col-md-10 p-4 overflow-auto bg-light">

            @if(session('success')) <div class="alert alert-success alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('warning') }}</div> @endif
            @if(session('error')) <div class="alert alert-danger alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div> @endif

            <div class="mb-4">
                <h2>Magazyn</h2>
                <p class="text-muted">Obsługa zapotrzebowań od techników, lista zakupów i stany magazynowe.</p>
            </div>

            <!-- KARTY KPI -->
            <div class="row mb-4">
                <div class="col-md-3"><div class="card shadow-sm border-0"><div class="card-body">
                    <h6 class="text-muted">Aktywne zapotrzebowania</h6><h3 class="text-orange fw-bold">{{ $kpiZap ?? 0 }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0"><div class="card-body">
                    <h6 class="text-muted">Lista zakupów</h6><h3 class="text-purple fw-bold">{{ $kpiZakupy ?? 0 }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0"><div class="card-body">
                    <h6 class="text-muted">Pozycje w magazynie</h6><h3 class="text-success fw-bold">{{ $kpiStan ?? 0 }}</h3>
                </div></div></div>
                <div class="col-md-3"><div class="card shadow-sm border-0"><div class="card-body">
                    <h6 class="text-muted">Sztuki łącznie</h6><h3 class="text-info fw-bold">{{ $kpiLacznie ?? 0 }}</h3>
                </div></div></div>
            </div>

            <!-- KONTENER NA ZAKŁADKI (ZAJMUJE CAŁĄ SZEROKOŚĆ) -->
            <div class="tab-content" id="v-pills-tabContent">

                <!-- 1. ZAPOTRZEBOWANIA -->
                <div class="tab-pane fade show active" id="zapotrzebowania">
                    <div class="card shadow-sm border-0 p-4">
                        <h5 class="fw-bold">Nowe zapotrzebowania od techników</h5>
                        <p class="text-muted small">Zaznacz pozycje, aby wydać je z magazynu lub dodać do listy zamówień.</p>

                        <form method="POST">
                            @csrf
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light text-muted small">
                                        <tr>
                                            <th></th>
                                            <th>ID Zap.</th>
                                            <th>ID Zlec.</th>
                                            <th>Model urządzenia</th>
                                            <th>Nazwa części</th>
                                            <th>Stan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($zapotrzebowania as $z)
                                            <tr>
                                                <td><input type="checkbox" class="form-check-input" name="wybrane_zap[]" value="{{ $z->zap_id }}"></td>
                                                <td>{{ $z->zap_id }}</td>
                                                <td class="fw-bold">#{{ $z->id_zlecenia }}</td>
                                                <td>{{ $z->model }}</td>
                                                <td>{{ $z->nazwa_czesci }}</td>
                                                <td>
                                                    @if($z->stan > 0)
                                                        <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-3 py-2">Jest ({{ $z->stan }})</span>
                                                    @else
                                                        <span class="badge bg-warning bg-opacity-25 text-warning rounded-pill px-3 py-2">Brak</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center py-4 text-muted">Brak oczekujących zapotrzebowań.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" formaction="{{ route('magazyn.wydaj') }}" class="btn btn-outline-secondary w-50">Wydaj zaznaczone</button>
                                <button type="submit" formaction="{{ route('magazyn.doZamowienia') }}" class="btn text-white w-50" style="background-color: #8b5cf6;">Dodaj do listy zamówień</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. ZAKUPY -->
                <div class="tab-pane fade" id="zakupy">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card shadow-sm border-0 p-4 h-100">
                                <h5 class="fw-bold">Lista zakupów</h5>
                                <p class="text-muted small">Po dostawie zaksięguj — pozycje trafią na stan.</p>

                                <div class="table-responsive flex-grow-1">
                                    <table class="table align-middle">
                                        <tbody>
                                            @forelse($zakupy as $z)
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold">{{ $z->model }}</div>
                                                        <div class="text-muted small">{{ $z->nazwa_czesci }}</div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="fw-bold me-3">{{ $z->ilosc }} szt.</span>
                                                        <button type="button" class="btn btn-sm btn-purple rounded-circle fw-bold text-white btn-add-qty" data-model="{{ $z->model }}" data-czesc="{{ $z->nazwa_czesci }}">+</button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="2" class="text-center py-4 text-muted">Lista zakupów jest pusta.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <form action="{{ route('magazyn.ksiegujDostawe') }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">+ Zaksięguj dostawę z listy zakupów</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="card shadow-sm border-0 p-4 h-100">
                                <h5 class="fw-bold">Ręczne uzupełnianie zapasów</h5>
                                <p class="text-muted small">Dodaj pozycję spoza zapotrzebowań.</p>

                                <form action="{{ route('magazyn.reczneZamowienie') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small">Typ urządzenia</label>
                                        <select id="select-typ" class="form-select bg-light border-0">
                                            <option value="">Wybierz...</option>
                                            @foreach($typy as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Model urządzenia</label>
                                        <select id="select-model" name="model" class="form-select bg-light border-0" disabled>
                                            <option value="">Najpierw wybierz typ...</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Dostępna część</label>
                                        <select id="select-czesc" name="czesc" class="form-select bg-light border-0" disabled>
                                            <option value="">Najpierw wybierz model...</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label small">Ilość sztuk</label>
                                        <input type="number" name="ilosc" class="form-control bg-light border-0" value="1" min="1" required>
                                    </div>
                                    <button type="submit" class="btn text-white w-100" style="background-color: #8b5cf6;">+ Dodaj do listy</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. STAN MAGAZYNU -->
                <div class="tab-pane fade" id="stan">
                    <div class="card shadow-sm border-0 p-4">
                        <h5 class="fw-bold">Aktualny stan magazynu</h5>
                        <p class="text-muted small">Wszystkie części dostępne do wydania technikom.</p>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th>ID</th>
                                        <th>Część (Model — Nazwa)</th>
                                        <th class="text-end">Sztuk na stanie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($stany as $s)
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td><span class="fw-bold">{{ $s->model }}</span> — <span class="text-muted">{{ $s->nazwa_czesci }}</span></td>
                                            <td class="text-end">
                                                @if($s->ilosc > 2)
                                                    <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-3 py-2 border border-success border-opacity-25">{{ $s->ilosc }} szt.</span>
                                                @else
                                                    <span class="badge bg-warning bg-opacity-25 text-warning rounded-pill px-3 py-2 border border-warning border-opacity-25">{{ $s->ilosc }} szt.</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center py-4 text-muted">Magazyn jest całkowicie pusty.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="qtyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Zwiększ ilość</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="{{ route('magazyn.szybkieDodanie') }}" method="POST">
          @csrf
          <div class="modal-body text-center">
            <p class="mb-1 fw-bold" id="modalModel"></p>
            <p class="text-muted small mb-3" id="modalCzesc"></p>
            <input type="hidden" name="model" id="inputModel">
            <input type="hidden" name="czesc" id="inputCzesc">

            <label class="form-label small">Ile dodatkowych sztuk?</label>
            <input type="number" name="ilosc" class="form-control bg-light border-0 text-center mx-auto" value="1" min="1" style="max-width: 100px;" required>
          </div>
          <div class="modal-footer border-0 pt-0 justify-content-center">
            <button type="submit" class="btn text-white w-100" style="background-color: #8b5cf6;">Dodaj do listy</button>
          </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<style>
    .text-orange { color: #f97316 !important; }
    .text-purple { color: #8b5cf6 !important; }
    .bg-purple { background-color: #8b5cf6 !important; color: #fff !important; }
    .btn-purple { background-color: #8b5cf6 !important; }
    .btn-purple:hover { background-color: #7c3aed !important; }

    /* Aktywne zakładki w lewym menu */
    .nav-pills .nav-link.active { background-color: #8b5cf6 !important; color: #ffffff !important; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075) !important; }
    .nav-pills .nav-link:not(.active) { border-color: transparent !important; color: #495057 !important; }
    .nav-pills .nav-link:not(.active):hover { background-color: #f8f9fa !important; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Aktywna zakładka po restarcie stony
    let aktywnaZakladka = localStorage.getItem('magazynAktywnaZakladka');
    if (aktywnaZakladka) {
        let triggerEl = document.querySelector('#v-pills-tab button[data-bs-target="' + aktywnaZakladka + '"]');
        if (triggerEl) {
            let tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }

    // Zapisywanie aktywnej zakładki do pamięci
    let tabElements = document.querySelectorAll('button[data-bs-toggle="pill"]');
    tabElements.forEach(function(el) {
        el.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('magazynAktywnaZakladka', event.target.getAttribute('data-bs-target'));
        });
    });

    // Obsługa Modala do szybkiego zwiększania ilości
    const qtyModal = new bootstrap.Modal(document.getElementById('qtyModal'));
    document.querySelectorAll('.btn-add-qty').forEach(btn => {
        btn.addEventListener('click', function() {
            const mod = this.getAttribute('data-model');
            const czes = this.getAttribute('data-czesc');

            document.getElementById('modalModel').innerText = mod;
            document.getElementById('modalCzesc').innerText = czes;
            document.getElementById('inputModel').value = mod;
            document.getElementById('inputCzesc').value = czes;

            qtyModal.show();
        });
    });

    // Dynamiczne Selecty w formularzu ręcznego zamawiania
    const selectTyp = document.getElementById('select-typ');
    const selectModel = document.getElementById('select-model');
    const selectCzesc = document.getElementById('select-czesc');

    selectTyp.addEventListener('change', function() {
        const typ = this.value;
        selectModel.innerHTML = '<option value="">Wybierz model...</option>';
        selectCzesc.innerHTML = '<option value="">Najpierw wybierz model...</option>';
        selectModel.disabled = true;
        selectCzesc.disabled = true;

        if(typ) {
            fetch(`/api/magazyn/modele/${encodeURIComponent(typ)}`)
                .then(res => res.json())
                .then(data => {
                    data.forEach(m => selectModel.innerHTML += `<option value="${m}">${m}</option>`);
                    selectModel.disabled = false;
                });
        }
    });

    selectModel.addEventListener('change', function() {
        const model = this.value;
        selectCzesc.innerHTML = '<option value="">Wybierz część...</option>';
        selectCzesc.disabled = true;

        if(model) {
            fetch(`/api/magazyn/czesci/${encodeURIComponent(model)}`)
                .then(res => res.json())
                .then(data => {
                    data.forEach(c => selectCzesc.innerHTML += `<option value="${c}">${c}</option>`);
                    selectCzesc.disabled = false;
                });
        }
    });
});
</script>
@endsection
