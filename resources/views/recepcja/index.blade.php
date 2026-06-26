@extends('layouts.app')

@section('title', 'ElectroService - Panel Recepcja')

@section('content')
<div class="container-fluid">
    <div class="row vh-100">
        <div class="col-md-2 bg-white border-end d-flex flex-column justify-content-between p-3">
            <div>
                <h4 class="fw-bold text-dark m-0">ElectroService</h4>
                <p class="text-muted small mb-4">Panel Recepcja</p>

                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active text-start mb-2" id="obsuga-tab" data-bs-toggle="pill" data-bs-target="#tab-obsluga" type="button" role="tab">Obsługa klienta</button>
                    <button class="nav-link text-start text-dark mb-2" id="katalog-tab" data-bs-toggle="pill" data-bs-target="#tab-katalog" type="button" role="tab">Katalog</button>

                    <button class="nav-link text-start text-dark mb-2" id="status-tab" data-bs-toggle="pill" data-bs-target="#tab-status" type="button" role="tab">Sprawdź status</button>
                </div>
            </div>

            <div class="border-top pt-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">RE</div>
                    <div class="ms-2">
                        <h6 class="mb-0 fw-bold small">recepcja</h6>
                        <span class="text-muted extra-small" style="font-size: 11px;">Recepcja</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Wyloguj</button>
                </form>
            </div>
        </div>

        <div class="col-md-10 p-4 overflow-auto">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="tab-content" id="v-pills-tabContent">

                <div class="tab-pane fade show active" id="tab-obsluga" role="tabpanel">
                    <div class="mb-4">
                        <h2>Recepcja</h2>
                        <p class="text-muted">Przyjmuj nowe zlecenia napraw i wydawaj gotowy sprzęt.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="card p-4 shadow-sm bg-white">
                                <h5 class="fw-bold mb-1">Nowe zlecenie naprawy</h5>
                                <p class="text-muted small mb-4">Wprowadź dane klienta oraz urządzenia.</p>

                                <form action="{{ route('recepcja.storeOrder') }}" method="POST" id="form-zlecenie" enctype="multipart/form-data">
                                    @csrf
                                    <h6 class="fw-bold mb-3">Dane klienta</h6>

                                    <!-- Pierwsza linia: Imię i Nazwisko -->
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label class="small text-muted mb-1">Imię</label>
                                            <input type="text" name="imie" maxlength="26" class="form-control bg-light border-0 @error('imie') is-invalid @enderror" value="{{ old('imie') }}" required>
                                            @error('imie') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small text-muted mb-1">Nazwisko</label>
                                            <input type="text" name="nazwisko" maxlength="26" class="form-control bg-light border-0 @error('nazwisko') is-invalid @enderror" value="{{ old('nazwisko') }}" required>
                                            @error('nazwisko') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <!-- Druga linia: Telefon -->
                                    <div class="row mb-4">
                                        <div class="col-md-4 mb-3 mb-md-0">
                                            <label class="small text-muted mb-1">Numer kierunkowy</label>
                                            <input type="text" name="kierunkowy" id="input-kierunkowy" maxlength="4" class="form-control bg-light border-0 @error('kierunkowy') is-invalid @enderror" placeholder="+48" value="{{ old('kierunkowy', '+48') }}">
                                            @error('kierunkowy') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-8">
                                            <label class="small text-muted mb-1">Numer telefonu</label>
                                            <input type="text" name="telefon" class="form-control bg-light border-0 @error('telefon') is-invalid @enderror" placeholder="600100200" value="{{ old('telefon') }}" required>
                                            @error('telefon') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-3">Dane urządzenia</h6>
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label class="small text-muted mb-1">Typ urządzenia</label>
                                            <select id="select-typ" name="typ" class="form-select bg-light border-0">
                                                <option value="">Wybierz...</option>
                                                @foreach($typy as $typ)
                                                    <option value="{{ $typ }}" {{ old('typ') == $typ ? 'selected' : '' }}>{{ $typ }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small text-muted mb-1">Model</label>
                                            <select id="select-model" name="model" class="form-select bg-light border-0 @error('model') is-invalid @enderror" data-old="{{ old('model') }}" disabled required>
                                                <option value="">Najpierw wybierz typ...</option>
                                            </select>
                                            @error('model') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small text-muted mb-1">Nr seryjny</label>
                                            <input type="text" name="numer_seryjny" maxlength="26" class="form-control bg-light border-0 @error('numer_seryjny') is-invalid @enderror" placeholder="SN..." value="{{ old('numer_seryjny') }}" required>
                                            @error('numer_seryjny') <div class="text-danger extra-small" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6 mt-3">
                                            <label class="small text-muted mb-1">Termin (data) naprawy</label>
                                            <select id="select-termin" name="data_naprawy" class="form-select bg-light border-0 @error('data_naprawy') is-invalid @enderror" data-old="{{ old('data_naprawy') }}" required>
                                                <option value="">Ładowanie wolnych terminów...</option>
                                            </select>
                                            @error('data_naprawy') <div class="text-danger extra-small mt-1" style="font-size: 11px;">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-12 mt-4">
                                            <label class="small text-muted mb-2">Zdjęcia sprzętu przed naprawą (opcjonalne)</label>

                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <div id="zdjecia-lista" class="d-flex flex-wrap gap-2"></div>

                                                <input type="file" name="zdjecia[]" id="input-zdjecia-ukryty" multiple accept="image/png, image/jpeg, image/jpg, image/webp" class="d-none @error('zdjecia.*') is-invalid @enderror">

                                                <button type="button" id="btn-dodaj-zdjecie" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="height: 70px; width: 140px; border-style: dashed; border-width: 2px;">
                                                    <span class="small fw-bold">+ Dodaj zdjęcie</span>
                                                </button>
                                            </div>

                                            @error('zdjecia.*')
                                                <div class="text-danger extra-small mt-1" style="font-size: 11px;">
                                                    Błąd zdjęcia: {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-2">Wybierz usługi / części</h6>
                                    <div class="border rounded p-3 bg-light overflow-auto" style="max-height: 150px;">
                                        <div id="kontener-czesci" class="row" data-old="{{ json_encode(old('czesci', [])) }}">
                                            <p class="text-muted small m-0 ps-2">Wybierz model urządzenia, aby zobaczyć pozycje cennikowe.</p>
                                        </div>
                                    </div>
                                    @error('czesci') <div class="text-danger extra-small mt-1" style="font-size: 11px;">{{ $message }}</div> @enderror
                                </form>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="card p-4 shadow-sm bg-white mb-4">
                                <h5 class="fw-bold mb-3">Podsumowanie</h5>
                                <div class="bg-purple text-white p-3 rounded mb-3">
                                    <span class="small d-block text-white-50">Całkowity koszt</span>
                                    <h2 class="m-0 fw-bold" id="suma-pln">0.00 PLN</h2>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" form="form-zlecenie" class="btn btn-success flex-grow-1">Akceptuj</button>
                                    <button type="button" id="btn-odrzuc" class="btn btn-outline-danger flex-grow-1">Odrzuć</button>
                                </div>
                            </div>

                            <div class="card p-4 shadow-sm bg-white">
                                <h6 class="fw-bold mb-3">Sprzęt gotowy do wydania</h6>
                                <div class="table-responsive" style="max-height: 250px;">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Klient</th>
                                                <th>Urządzenie</th>
                                                <th>Koszt</th>
                                                <th>Akcja</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($gotoweZlecenia as $zl)
                                            @php $kosztWzrosl = $zl->koszt_pierwotny !== null && $zl->koszt > $zl->koszt_pierwotny; @endphp
                                            <tr>
                                                <td>{{ $zl->id }}</td>
                                                <td>{{ $zl->imie }} {{ $zl->nazwisko }}</td>
                                                <td>{{ $zl->model }}</td>
                                                <td class="fw-bold">
                                                    {{ number_format($zl->koszt, 2) }} PLN
                                                    @if($kosztWzrosl)
                                                        <span class="d-block text-danger" style="font-size: 11px;">
                                                            ↑ wzrost z {{ number_format($zl->koszt_pierwotny, 2) }} PLN
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($kosztWzrosl)
                                                        <div class="d-flex flex-column gap-1">
                                                            <span class="badge bg-warning text-dark mb-1">Koszt wzrósł — decyzja klienta</span>
                                                            <div class="d-flex gap-1">
                                                                <form action="{{ url('/recepcja/wydaj/'.$zl->id) }}" method="POST" class="m-0">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2">Akceptuje</button>
                                                                </form>
                                                                <form action="{{ route('recepcja.rejectCost', $zl->id) }}" method="POST" class="m-0">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">Odrzuca</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <form action="{{ url('/recepcja/wydaj/'.$zl->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-primary py-1 px-2">Wydaj</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted small py-3">Brak sprzętu gotowego do odbioru.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-katalog" role="tabpanel">
                    <div class="mb-4">
                        <h2>Katalog urządzeń i cennika</h2>
                        <p class="text-muted">Zarządzaj strukturą urządzeń, modelami oraz bazą cennika usług.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card p-4 shadow-sm bg-white h-100">
                                <h5 class="fw-bold">+ Dodaj nowy Typ urządzenia</h5>
                                <p class="text-muted small mb-4">Utwórz nową kategorię główną</p>

                                <form action="{{ route('recepcja.storeType') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Nazwa nowego typu:</label>
                                        <input type="text" name="nazwa_typu" class="form-control bg-light border-0" placeholder="Nazwa typu" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 mt-2" style="background-color: #8b5cf6; border: none;">+ Utwórz Typ</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="card p-4 shadow-sm bg-white h-100">
                                <h5 class="fw-bold">+ Dodaj nowe urządzenie (Model)</h5>
                                <p class="text-muted small mb-4">Przypisz konkretny model do istniejącej kategorii.</p>

                                <form action="{{ route('recepcja.storeModel') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Wybierz Typ:</label>
                                        <select name="nowy_typ" id="select-katalog-typ" class="form-select bg-light border-0" required>
                                            @foreach($typy as $typ)
                                                <option value="{{ $typ }}">{{ $typ }}</option>
                                            @endforeach
                                            <option value="Inny">Inny (Wpisz ręcznie)</option>
                                        </select>
                                    </div>
                                    <div id="manual-typ-frame" class="mb-3 d-none">
                                        <label class="form-label small text-muted mb-1">Wpisz nową kategorię:</label>
                                        <input type="text" name="nowy_typ_manual" class="form-control bg-light border-0">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Nazwa modelu:</label>
                                        <input type="text" name="model" class="form-control bg-light border-0" placeholder="Nazwa modelu" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 mt-2">+ Dodaj model</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="card p-4 shadow-sm bg-white h-100">
                                <h5 class="fw-bold">+ Dodaj część lub usługę</h5>
                                <p class="text-muted small mb-4">Rozbuduj pozycje cennikowe dla wybranego sprzętu.</p>

                                @if(session('ask_overwrite'))
                                    <div class="alert alert-warning shadow-sm border-warning p-2 small mb-3">
                                        <strong>Istnieje!</strong> {{ session('ask_overwrite') }}
                                        <form action="{{ route('recepcja.storePart') }}" method="POST" class="mt-2 d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="katalog_typ" value="{{ old('katalog_typ') }}">
                                            <input type="hidden" name="katalog_model" value="{{ old('katalog_model') }}">
                                            <input type="hidden" name="nazwa_czesci" value="{{ old('nazwa_czesci') }}">
                                            <input type="hidden" name="cena" value="{{ old('cena') }}">
                                            <input type="hidden" name="typ_pozycji" value="{{ old('typ_pozycji') }}">
                                            <input type="hidden" name="force_overwrite" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger py-1">Nadpisz</button>
                                            <a href="{{ url('/recepcja') }}" class="btn btn-sm btn-outline-secondary py-1 bg-white">Anuluj</a>
                                        </form>
                                    </div>
                                @endif

                                <form action="{{ route('recepcja.storePart') }}" method="POST">
                                    @csrf
                                    <div class="mb-2">
                                        <select name="katalog_typ" id="select-katalog-typ-czesc" class="form-select form-select-sm bg-light border-0" required>
                                            <option value="">Wybierz typ...</option>
                                            @foreach($typy as $typ)
                                                <option value="{{ $typ }}" {{ old('katalog_typ') == $typ ? 'selected' : '' }}>{{ $typ }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select name="katalog_model" id="select-katalog-model-czesc" class="form-select form-select-sm bg-light border-0" data-old="{{ old('katalog_model') }}" disabled required>
                                            <option value="">Najpierw wybierz typ...</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="nazwa_czesci" class="form-control form-control-sm bg-light border-0" value="{{ old('nazwa_czesci') }}" placeholder="Nazwa części/usługi" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-7">
                                            <input type="number" step="0.01" name="cena" class="form-control form-control-sm bg-light border-0" value="{{ old('cena') }}" placeholder="Cena PLN" required>
                                        </div>
                                        <div class="col-5">
                                            <select name="typ_pozycji" class="form-select form-select-sm bg-light border-0" required>
                                                <option value="Część" {{ old('typ_pozycji') == 'Część' ? 'selected' : '' }}>Część</option>
                                                <option value="Usługa" {{ old('typ_pozycji') == 'Usługa' ? 'selected' : '' }}>Usługa</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-dark btn-sm w-100">+ Dodaj do cennika</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-12 mb-4">
                            <div class="card p-4 shadow-sm bg-white">
                                <h5 class="fw-bold">Edytuj lub usuń pozycje cennika</h5>
                                <p class="text-muted small mb-3">Wybierz typ i model, aby zarządzać cenami oraz usuwać pozycje.</p>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <select id="edit-katalog-typ" class="form-select form-select-sm bg-light border-0">
                                            <option value="">Wybierz typ...</option>
                                            @foreach($typy as $typ)
                                                <option value="{{ $typ }}">{{ $typ }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select id="edit-katalog-model" class="form-select form-select-sm bg-light border-0" disabled>
                                            <option value="">Najpierw wybierz typ...</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nazwa</th>
                                                <th style="width: 320px;">Cena / typ</th>
                                                <th style="width: 90px;" class="text-end">Usuń</th>
                                            </tr>
                                        </thead>
                                        <tbody id="lista-pozycji-cennika">
                                            <tr><td colspan="3" class="text-center text-muted small py-3">Wybierz model, aby wyświetlić pozycje cennika.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-status" role="tabpanel">
                    <div class="mb-4">
                        <h2>Status naprawy</h2>
                        <p class="text-muted">Szybka weryfikacja zlecenia</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <!-- KARTA STATUSU -->
                            <div class="card p-4 shadow-sm bg-white mb-4 border-start border-4 border-info">
                                <h5 class="fw-bold mb-2">Sprawdź status naprawy</h5>
                                <p class="text-muted small mb-3">Wpisz numer zlecenia oraz numer seryjny urządzenia.</p>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-0 fw-bold text-muted">#</span>
                                            <input type="number" id="status-zlecenie" class="form-control bg-light border-0" placeholder="Zlecenie">
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" id="status-numer-seryjny" class="form-control bg-light border-0" placeholder="Numer seryjny">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-info text-white fw-bold w-100" type="button" id="btn-check-status">Szukaj</button>
                                    </div>
                                </div>

                                <div id="status-result" class="d-none rounded p-3 bg-light"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('nowe_zlecenie'))
    @php $noweZlecenie = session('nowe_zlecenie'); @endphp
    <div class="modal fade" id="zlecenieModal" tabindex="-1" aria-hidden="true"
         data-zlecenie-id="{{ $noweZlecenie['id'] }}">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-success text-white border-0">
            <h5 class="modal-title fw-bold">Zlecenie przyjęte</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3">Zlecenie zostało zapisane w systemie. Poniżej dane potwierdzające.</p>
            <div class="bg-light rounded p-3 mb-2 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Numer zlecenia</span>
                <span class="fw-bold fs-5">#{{ $noweZlecenie['id'] }}</span>
            </div>
            <div class="bg-light rounded p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Numer seryjny</span>
                <span class="fw-bold">{{ $noweZlecenie['numer_seryjny'] }}</span>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btn-zamknij-zlecenie">Zamknij</button>
            <a href="{{ route('recepcja.wydruk', session('nowe_zlecenie.id')) }}" target="_blank" class="btn btn-primary" onclick="this.classList.add('disabled'); this.innerText='Otwarto do druku...';"> Wydrukuj zlecenie </a>
          </div>
        </div>
      </div>
    </div>
@endif
@endsection

@section('scripts')
<script src="{{ asset('js/recepcja.js') }}"></script>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
    <div id="updateToastRecepcja" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="d-flex">
            <div class="toast-body">
                <strong>Aktualizacja!</strong> Sprzęt przeszedł kontrolę i jest gotowy do wydania.
                <button type="button" class="btn btn-light btn-sm ms-2 mt-2 w-100" onclick="window.location.reload();">Odśwież stronę</button>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pobiera ilość urządzeń gotowych do wydania z głównego zapytania
    let currentCount = {{ $gotoweZlecenia->count() ?? 0 }};

    setInterval(() => {
        fetch('/api/recepcja/check-updates')
            .then(response => response.json())
            .then(data => {
                if (data.count > currentCount) {
                    const toastEl = document.getElementById('updateToastRecepcja');
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                    currentCount = data.count;
                } else if (data.count < currentCount) {
                    currentCount = data.count;
                }
            })
            .catch(err => console.error(err));
    }, 10000);
});
</script>
@endsection
