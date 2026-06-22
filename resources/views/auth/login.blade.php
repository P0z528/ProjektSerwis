@extends('layouts.app')

@section('title', 'ElectroService - Logowanie')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0 vh-100">
        <div class="col-md-4 d-flex flex-column justify-content-center align-items-center bg-white shadow-lg z-index-1">
            <div class="w-75">
                <div class="mb-5 text-center">
                    <div class="rounded-circle bg-purple text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <span class="fs-3 fw-bold">ES</span>
                    </div>
                    <h2 class="fw-bold">Witaj ponownie</h2>
                    <p class="text-muted">Zaloguj się do systemu ElectroService.</p>
                </div>

                @if($errors->has('auth'))
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3">
                        <i class="bi bi-exclamation-circle me-2"></i> {{ $errors->first('auth') }}
                    </div>
                @endif

                <form action="{{ url('/login') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Login</label>
                        <input type="text" name="login" class="form-control form-control-lg bg-light border-0" placeholder="Wpisz swój login" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted fw-bold">Hasło</label>
                        <input type="password" name="haslo" class="form-control form-control-lg bg-light border-0" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-lg w-100 text-white fw-bold" style="background-color: #8b5cf6;">
                        Zaloguj się
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-8 bg-light d-flex flex-column justify-content-center align-items-center p-5">
            <div class="text-center mb-4">
                <h3 class="fw-bold">Kalendarz dostępności serwisu</h3>
                <p class="text-muted">Sprawdź wolne terminy napraw</p>
            </div>

            <div class="card border-0 shadow-sm w-100 p-4" style="max-width: 900px; border-radius: 15px;">
                <div id="calendar"></div>
                <div class="d-flex flex-wrap gap-3 justify-content-center mt-4 small text-muted">
                    <span><span class="badge" style="background-color:#10b981;">&nbsp;</span> Wolne terminy</span>
                    <span><span class="badge" style="background-color:#f59e0b;">&nbsp;</span> Mało miejsc</span>
                    <span><span class="badge" style="background-color:#ef4444;">&nbsp;</span> Brak miejsc</span>
                    <span><span class="badge" style="background-color:#6b7280;">&nbsp;</span> Nieczynne (niedziele)</span>
                    <span><span class="badge" style="background-color:#d1d5db;">&nbsp;</span> Niedostępne (przeszłość)</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pl',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        buttonText: {
            today: 'Dzisiaj',
            month: 'Miesiąc'
        },
        displayEventTime: false,
        // Dane pobierane dynamicznie z API Laravela (limity i zajętość terminów)
        events: '/api/kalendarz/feed'
    });
    calendar.render();
});
</script>
@endsection
