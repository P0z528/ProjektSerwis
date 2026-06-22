@extends('layouts.app')

@section('title', 'ElectroService - Panel Administrator')

@section('content')
<div class="container-fluid">
    <div class="row vh-100">
        <div class="col-md-2 bg-white border-end d-flex flex-column justify-content-between p-3">
            <div>
                <h4 class="fw-bold text-dark m-0">ElectroService</h4>
                <p class="text-muted small mb-4">Panel Administratora</p>

                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active text-start mb-2" id="tab-btn-dashboard" data-bs-toggle="pill" data-bs-target="#tab-dashboard" type="button" role="tab">Dashboard</button>
                    <button class="nav-link text-start text-dark mb-2" id="tab-btn-kontrola" data-bs-toggle="pill" data-bs-target="#tab-kontrola" type="button" role="tab">Kontrola jakości</button>
                    <button class="nav-link text-start text-dark mb-2" id="tab-btn-klienci" data-bs-toggle="pill" data-bs-target="#tab-klienci" type="button" role="tab">Klienci</button>
                    <button class="nav-link text-start text-dark mb-2" id="tab-btn-pracownicy" data-bs-toggle="pill" data-bs-target="#tab-pracownicy" type="button" role="tab">Pracownicy</button>
                </div>
            </div>

            <div class="border-top pt-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">AD</div>
                    <div class="ms-2">
                        <h6 class="mb-0 fw-bold small">admin</h6>
                        <span class="text-muted extra-small" style="font-size: 11px;">Administrator</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Wyloguj</button>
                </form>
            </div>
        </div>

        <div class="col-md-10 p-4 overflow-auto bg-light">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('warning') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="tab-content">

                <div class="tab-pane fade show active" id="tab-dashboard" role="tabpanel">
                    <div class="mb-4">
                        <h2>Dashboard administratora</h2>
                        <p class="text-muted">Podgląd statusów napraw, kontrola jakości i metryki serwisu.</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0"><div class="card-body">
                                <h6 class="text-muted">Aktywne zlecenia</h6>
                                <h3>{{ $aktywne }}</h3>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0"><div class="card-body">
                                <h6 class="text-muted">W naprawie</h6>
                                <h3 class="text-info">{{ $wNaprawie }}</h3>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0"><div class="card-body">
                                <h6 class="text-muted">Do wydania</h6>
                                <h3 class="text-success">{{ $doWydania }}</h3>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0"><div class="card-body">
                                <h6 class="text-muted">Przychód</h6>
                                <h3 class="text-warning">{{ number_format($przychod, 0) }}</h3>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body text-center">
                                    <h5 class="fw-bold text-start">Statusy urządzeń</h5>
                                    <p class="text-muted small text-start">Rozkład bieżących stanów napraw.</p>
                                    <div style="height: 250px; display:flex; justify-content:center;">
                                        <canvas id="donutChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body">
                                    <h5 class="fw-bold text-start">Urządzenia w serwisie</h5>
                                    <p class="text-muted small text-start">Liczba aktywnych zleceń wg kategorii.</p>
                                    <div style="height: 250px;">
                                        <canvas id="barChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-kontrola" role="tabpanel">
                    <div class="mb-4">
                        <h2>Kontrola jakości</h2>
                        <p class="text-muted">Naprawy oczekujące na zatwierdzenie przed wydaniem klientowi.</p>
                    </div>

                    <div class="card shadow-sm border-0 p-3">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>ID</th>
                                    <th>Urządzenie</th>
                                    <th>Technik</th>
                                    <th>Usterka</th>
                                    <th>Koszt</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($doKontroli as $zl)
                                <tr>
                                    <td class="fw-bold">#{{ $zl->id }}</td>
                                    <td>{{ $zl->model }}</td>
                                    <td class="text-muted">{{ $zl->technik ?? 'Brak' }}</td>
                                    <td>
                                        <div>{{ Str::limit($zl->opis_usterki, 40) }}</div>
                                        @if(isset($zl->czesci) && count($zl->czesci) > 0)
                                            <ul class="list-unstyled mb-0 mt-2 small">
                                                @foreach($zl->czesci as $poz)
                                                    <li class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                                        <span>
                                                            {{ $poz->nazwa_czesci }}
                                                            <span class="text-muted">({{ $poz->typ }})</span>
                                                            @if($poz->dodatkowa)
                                                                <span class="badge bg-warning bg-opacity-25 text-warning ms-1">Dodatkowa</span>
                                                            @endif
                                                        </span>
                                                        <span class="text-muted">{{ number_format($poz->cena, 2) }} PLN</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="fw-bold">{{ number_format($zl->koszt, 2) }} PLN</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-reject"
                                                    data-id="{{ $zl->id }}" data-model="{{ $zl->model }}">Poprawka</button>
                                            <form action="{{ route('admin.approve', $zl->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Zatwierdź</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">Brak urządzeń oczekujących na kontrolę.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-klienci" role="tabpanel">
                    <div class="mb-4">
                        <h2>Klienci</h2>
                        <p class="text-muted">Aktywne zlecenia klientów — edycja statusu i usuwanie urządzeń.</p>
                    </div>

                    <div class="card shadow-sm border-0 p-3">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>Zlecenie</th>
                                    <th>Klient</th>
                                    <th>Urządzenie</th>
                                    <th>Nr seryjny</th>
                                    <th>Status</th>
                                    <th>Koszt</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($zleceniaKlientow as $zl)
                                <tr>
                                    <td class="fw-bold">#{{ $zl->id_zlecenia }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $zl->imie }} {{ $zl->nazwisko }}</div>
                                        <div class="small text-muted">{{ $zl->telefon ?? '—' }}</div>
                                    </td>
                                    <td>{{ $zl->model }}</td>
                                    <td class="text-muted small">{{ $zl->numer_seryjny }}</td>
                                    <td><span class="badge bg-secondary bg-opacity-25 text-dark">{{ $zl->status }}</span></td>
                                    <td class="fw-bold">{{ number_format($zl->koszt, 2) }} PLN</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-client"
                                                    data-id="{{ $zl->id_klienta }}"
                                                    data-zlecenie-id="{{ $zl->id_zlecenia }}"
                                                    data-status="{{ $zl->status }}"
                                                    data-model="{{ $zl->model }}"
                                                    data-imie="{{ $zl->imie }}"
                                                    data-nazwisko="{{ $zl->nazwisko }}"
                                                    data-telefon="{{ $zl->telefon }}">Edytuj klienta</button>
                                            <form action="{{ route('admin.deleteOrder', $zl->id_zlecenia) }}" method="POST" class="m-0"
                                                  onsubmit="return confirm('Na pewno usunąć to zlecenie i powiązane urządzenie? Tej operacji nie można cofnąć.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center py-4 text-muted">Brak aktywnych zleceń klientów.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-pracownicy" role="tabpanel">
                    <div class="mb-4">
                        <h2>Pracownicy</h2>
                        <p class="text-muted">Dodawaj nowe konta pracowników i zarządzaj rolami.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm border-0 p-4 h-100">
                                <h5 class="fw-bold">+ Dodaj pracownika</h5>
                                <p class="text-muted small mb-3">Utwórz nowe konto i przypisz rolę.</p>
                                <form action="{{ route('admin.storeEmployee') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="small text-muted mb-1">Login</label>
                                        <input type="text" name="login" class="form-control bg-light border-0" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small text-muted mb-1">Hasło</label>
                                        <input type="text" name="haslo" class="form-control bg-light border-0" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small text-muted mb-1">Rola</label>
                                        <select name="rola" class="form-select bg-light border-0" required>
                                            <option value="Recepcja">Recepcja</option>
                                            <option value="Technik">Technik</option>
                                            <option value="Magazyn">Magazyn</option>
                                            <option value="Admin">Admin</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn w-100 text-white fw-bold" style="background-color:#8b5cf6;">+ Dodaj pracownika</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-8 mb-4">
                            <div class="card shadow-sm border-0 p-3 h-100">
                                <h5 class="fw-bold mb-3">Lista pracowników</h5>
                                <table class="table table-hover align-middle">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>ID</th>
                                            <th>Login</th>
                                            <th>Rola</th>
                                            <th class="text-end">Akcje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pracownicy as $p)
                                        <tr>
                                            <td class="fw-bold">#{{ $p->id }}</td>
                                            <td>{{ $p->login }}</td>
                                            <td>
                                                <span class="badge bg-secondary bg-opacity-25 text-dark">{{ $p->rola }}</span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-employee"
                                                            data-id="{{ $p->id }}"
                                                            data-login="{{ $p->login }}"
                                                            data-rola="{{ $p->rola }}"
                                                            data-ostatni="{{ $p->ostatni_w_roli ? '1' : '0' }}">Edytuj</button>
                                                    @if($p->ostatni_w_roli)
                                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Nie można usunąć ostatniej osoby w tej roli">Usuń</button>
                                                    @else
                                                        <form action="{{ route('admin.deleteEmployee', $p->id) }}" method="POST" class="m-0" onsubmit="return confirm('Na pewno usunąć tego pracownika?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">Brak pracowników.</td></tr>
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
</div>
{{-- MODAL: Powód odrzucenia (Kontrola jakości) --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="rejectForm" method="POST" action="">
        @csrf
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Odrzucenie do poprawki</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3" id="rejectModelText">Zlecenie</p>
          <label class="small text-muted mb-1">Powód odrzucenia — co zostało źle wykonane?</label>
          <select id="rejectPreset" class="form-select bg-light border-0 mb-2">
            <option value="">— Wybierz typowy powód lub wpisz własny —</option>
            <option value="Usterka nadal występuje po naprawie.">Usterka nadal występuje po naprawie.</option>
            <option value="Źle zamontowana część.">Źle zamontowana część.</option>
            <option value="Zamontowano niewłaściwą część.">Zamontowano niewłaściwą część.</option>
            <option value="Uszkodzenie obudowy podczas naprawy.">Uszkodzenie obudowy podczas naprawy.</option>
            <option value="Brak testu końcowego urządzenia.">Brak testu końcowego urządzenia.</option>
          </select>
          <textarea name="powod_odrzucenia" id="rejectReason" class="form-control bg-light border-0" rows="3" placeholder="Opisz dokładnie, co należy poprawić..." required></textarea>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-danger">Odeślij do poprawki</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL: Edycja klienta i statusu zlecenia --}}
<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="clientForm" method="POST" action="">
        @csrf
        <input type="hidden" name="id_zlecenia" id="clientZlecenieId">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Edycja klienta i zlecenia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3" id="clientZlecenieText">Zlecenie</p>
          <div class="mb-3">
            <label class="small text-muted mb-1">Imię</label>
            <input type="text" name="imie" id="clientImie" class="form-control bg-light border-0" required>
          </div>
          <div class="mb-3">
            <label class="small text-muted mb-1">Nazwisko</label>
            <input type="text" name="nazwisko" id="clientNazwisko" class="form-control bg-light border-0" required>
          </div>
          <div class="mb-3">
            <label class="small text-muted mb-1">Telefon</label>
            <input type="text" name="telefon" id="clientTelefon" class="form-control bg-light border-0">
          </div>
          <div class="mb-3">
            <label class="small text-muted mb-1">Status zlecenia</label>
            <select name="status" id="clientStatus" class="form-select bg-light border-0" required>
              @foreach($statusyZlecen as $status)
                <option value="{{ $status }}">{{ $status }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-success">Zapisz zmiany</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL: Edycja pracownika --}}
<div class="modal fade" id="employeeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="employeeForm" method="POST" action="">
        @csrf
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Edycja pracownika</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="small text-muted mb-1">Login</label>
            <input type="text" name="login" id="empLogin" class="form-control bg-light border-0" required>
          </div>
          <div class="mb-3">
            <label class="small text-muted mb-1">Nowe hasło <span class="text-muted">(zostaw puste, aby nie zmieniać)</span></label>
            <input type="text" name="haslo" id="empHaslo" class="form-control bg-light border-0" placeholder="••••••••">
          </div>
          <div class="mb-3">
            <label class="small text-muted mb-1">Rola</label>
            <select name="rola" id="empRola" class="form-select bg-light border-0" required>
              <option value="Recepcja">Recepcja</option>
              <option value="Technik">Technik</option>
              <option value="Magazyn">Magazyn</option>
              <option value="Admin">Admin</option>
            </select>
            {{-- Gdy pracownik jest ostatni w roli, select jest disabled i nie wysyła wartości - wysyłamy ją ukrytym polem --}}
            <input type="hidden" id="empRolaHidden">
            <div id="empRolaLock" class="text-muted small mt-1 d-none">
              Zmiana roli jest zablokowana. Można zmienić tylko login i hasło.
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-success">Zapisz zmiany</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // --- Zakładki + zapamiętywanie aktywnej zakładki (pozostajemy na stronie po akcji) ---
    const tabButtons = document.querySelectorAll('#v-pills-tab button');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            localStorage.setItem('adminAktywnaZakladka', this.id);
            tabButtons.forEach(b => { b.classList.remove('active', 'bg-purple', 'text-white'); b.classList.add('text-dark'); });
            this.classList.add('active', 'bg-purple', 'text-white');
        });
    });

    // Odtworzenie ostatnio aktywnej zakładki po przeładowaniu (np. po akcji Kontroli jakości)
    const zapisanaZakladka = localStorage.getItem('adminAktywnaZakladka');
    if (zapisanaZakladka) {
        const przycisk = document.getElementById(zapisanaZakladka);
        if (przycisk) bootstrap.Tab.getOrCreateInstance(przycisk).show();
    }

    // --- MODAL: Powód odrzucenia (QA) ---
    const rejectModalEl = document.getElementById('rejectModal');
    if (rejectModalEl) {
        const rejectModal = new bootstrap.Modal(rejectModalEl);
        const rejectForm = document.getElementById('rejectForm');
        const rejectPreset = document.getElementById('rejectPreset');
        const rejectReason = document.getElementById('rejectReason');

        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                rejectForm.action = `/admin/poprawka/${id}`;
                document.getElementById('rejectModelText').innerText = `Zlecenie #${id} — ${this.dataset.model}`;
                rejectReason.value = '';
                rejectPreset.value = '';
                rejectModal.show();
            });
        });

        rejectPreset.addEventListener('change', function() {
            if (this.value) rejectReason.value = this.value;
        });
    }

    // --- MODAL: Edycja klienta i statusu zlecenia ---
    const clientModalEl = document.getElementById('clientModal');
    if (clientModalEl) {
        const clientModal = new bootstrap.Modal(clientModalEl);
        const clientForm = document.getElementById('clientForm');
        document.querySelectorAll('.btn-edit-client').forEach(btn => {
            btn.addEventListener('click', function() {
                clientForm.action = `/admin/klient/${this.dataset.id}`;
                document.getElementById('clientZlecenieId').value = this.dataset.zlecenieId || '';
                document.getElementById('clientZlecenieText').innerText = `Zlecenie #${this.dataset.zlecenieId || ''} — ${this.dataset.model || ''}`;
                document.getElementById('clientImie').value = this.dataset.imie || '';
                document.getElementById('clientNazwisko').value = this.dataset.nazwisko || '';
                document.getElementById('clientTelefon').value = this.dataset.telefon || '';
                document.getElementById('clientStatus').value = this.dataset.status || 'W kolejce';
                clientModal.show();
            });
        });
    }

    // --- MODAL: Edycja pracownika ---
    const employeeModalEl = document.getElementById('employeeModal');
    if (employeeModalEl) {
        const employeeModal = new bootstrap.Modal(employeeModalEl);
        const employeeForm = document.getElementById('employeeForm');
        const empRola = document.getElementById('empRola');
        const empRolaHidden = document.getElementById('empRolaHidden');
        const empRolaLock = document.getElementById('empRolaLock');

        document.querySelectorAll('.btn-edit-employee').forEach(btn => {
            btn.addEventListener('click', function() {
                const rola = this.dataset.rola || 'Recepcja';
                const ostatni = this.dataset.ostatni === '1';

                employeeForm.action = `/admin/pracownik/${this.dataset.id}`;
                document.getElementById('empLogin').value = this.dataset.login || '';
                document.getElementById('empHaslo').value = '';
                empRola.value = rola;

                if (ostatni) {
                    // Ostatnia osoba w roli: blokujemy select, ale rolę przesyłamy ukrytym polem
                    empRola.disabled = true;
                    empRola.name = '';
                    empRolaHidden.name = 'rola';
                    empRolaHidden.value = rola;
                    empRolaLock.classList.remove('d-none');
                } else {
                    empRola.disabled = false;
                    empRola.name = 'rola';
                    empRolaHidden.name = '';
                    empRolaLock.classList.add('d-none');
                }

                employeeModal.show();
            });
        });
    }

    // --- WYKRES 1: DONUT (Statusy) ---
    const donutCtx = document.getElementById('donutChart').getContext('2d');
    const dLabels = @json($donutLabels);
    const dData = @json($donutData);

    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: dLabels.length > 0 ? dLabels : ['Brak napraw'],
            datasets: [{
                data: dData.length > 0 ? dData : [1],
                backgroundColor: dData.length > 0 ? ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'] : ['#e9ecef'],
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '70%' // Grubość pierścienia
        }
    });

    // --- WYKRES 2: BAR (Modele) ---
    const barCtx = document.getElementById('barChart').getContext('2d');
    const bLabels = @json($barLabels);
    const bData = @json($barData);

    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: bLabels.length > 0 ? bLabels : ['Brak danych'],
            datasets: [{
                label: 'Ilość urządzeń',
                data: bData.length > 0 ? bData : [0],
                backgroundColor: '#8b5cf6', // Nasz kolor główny
                borderRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
@endsection
