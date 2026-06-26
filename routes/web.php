<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReceptionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\MagazynController;
use App\Http\Controllers\KalendarzController;

// Ekran logowania
Route::get('/', function () { return redirect('/login'); });
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Publiczny feed kalendarza dostępności (ekran logowania)
Route::get('/api/kalendarz/feed', [KalendarzController::class, 'feed']);

// Zabezpieczone ścieżki dla zalogowanych użytkowników
Route::middleware(['auth'])->group(function () {

    // Panel Recepcji
    Route::get('/recepcja', [ReceptionController::class, 'index'])->name('recepcja.index');
    Route::get('/api/recepcja/status-klienta', [ReceptionController::class, 'checkClientStatus']);
    Route::get('/api/recepcja/status-naprawy', [ReceptionController::class, 'checkOrderStatus']);
    Route::get('/api/recepcja/terminy', [KalendarzController::class, 'dostepneTerminy']);
    Route::post('/recepcja/odrzuc-koszt/{id}', [ReceptionController::class, 'rejectCost'])->name('recepcja.rejectCost');
    Route::post('/recepcja/zlecenie', [ReceptionController::class, 'storeOrder'])->name('recepcja.storeOrder');
    Route::get('/recepcja/wydruk/{id}', [ReceptionController::class, 'downloadWydruk'])->name('recepcja.wydruk');
    Route::post('/recepcja/model', [ReceptionController::class, 'storeModel'])->name('recepcja.storeModel');
    Route::post('/recepcja/typ', [ReceptionController::class, 'storeType'])->name('recepcja.storeType');
    Route::post('/recepcja/czesc', [ReceptionController::class, 'storePart'])->name('recepcja.storePart');
    Route::post('/recepcja/czesc/{id}', [ReceptionController::class, 'updatePart'])->name('recepcja.updatePart');
    Route::post('/recepcja/czesc/{id}/usun', [ReceptionController::class, 'deletePart'])->name('recepcja.deletePart');
    Route::get('/api/katalog/{model}', [ReceptionController::class, 'getCatalogByModel']);
    Route::post('/recepcja/wydaj/{id}', [ReceptionController::class, 'releaseDevice'])->name('recepcja.releaseDevice');



    // Panel Administratora
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/zatwierdz/{id}', [AdminController::class, 'approve'])->name('admin.approve');
    Route::post('/admin/poprawka/{id}', [AdminController::class, 'reject'])->name('admin.reject');

    // Zarządzanie klientami i zleceniami
    Route::post('/admin/klient/{id}', [AdminController::class, 'updateClient'])->name('admin.updateClient');
    Route::post('/admin/zlecenie/{id}/usun', [AdminController::class, 'deleteOrder'])->name('admin.deleteOrder');

    // Zarządzanie pracownikami
    Route::post('/admin/pracownik', [AdminController::class, 'storeEmployee'])->name('admin.storeEmployee');
    Route::post('/admin/pracownik/{id}', [AdminController::class, 'updateEmployee'])->name('admin.updateEmployee');
    Route::post('/admin/pracownik/{id}/usun', [AdminController::class, 'deleteEmployee'])->name('admin.deleteEmployee');

    // Panel Technika
    Route::get('/technik', [TechnicianController::class, 'index'])->name('technik.index');
    Route::post('/technik/wez/{id}', [TechnicianController::class, 'takeOrder'])->name('technik.takeOrder');
    Route::post('/technik/gotowe/{id}', [TechnicianController::class, 'finishOrder'])->name('technik.finishOrder');
    Route::post('/technik/czesci/{id}', [TechnicianController::class, 'orderParts'])->name('technik.orderParts');

    // Panel Magazynu
    Route::get('/magazyn', [MagazynController::class, 'index'])->name('magazyn.index');
    Route::post('/magazyn/wydaj', [MagazynController::class, 'wydaj'])->name('magazyn.wydaj');
    Route::post('/magazyn/do-zamowienia', [MagazynController::class, 'przeniesDoZamowienia'])->name('magazyn.doZamowienia');
    Route::post('/magazyn/reczne-zamowienie', [MagazynController::class, 'reczneZamowienie'])->name('magazyn.reczneZamowienie');
    Route::post('/magazyn/dostawa', [MagazynController::class, 'ksiegujDostawe'])->name('magazyn.ksiegujDostawe');
    Route::post('/magazyn/dodaj-ilosc', [MagazynController::class, 'szybkieDodanieIlosci'])->name('magazyn.szybkieDodanie');

    // API dla dropdownów
    Route::get('/api/magazyn/modele/{typ}', [MagazynController::class, 'getModele']);
    Route::get('/api/magazyn/czesci/{model}', [MagazynController::class, 'getCzesci']);

    // API dla wyskakującego okienka z częściami (per zlecenie - z oznaczeniem części wymaganych)
    Route::get('/api/technik/czesci-dla-zlecenia/{id}', [TechnicianController::class, 'getPartsForOrder']);

    // Dynamiczne API dla JavaScriptu (zastępuje zapytania SQL w locie z Pythona)
    Route::get('/api/modele/{typ}', [ReceptionController::class, 'getModelsByType']);
    Route::get('/api/czesci/{model}', [ReceptionController::class, 'getPartsByModel']);

    // API do powiadomień w tle
    Route::get('/api/technik/check-updates', [TechnicianController::class, 'checkUpdates']);
    Route::get('/api/magazyn/check-updates', [MagazynController::class, 'checkUpdates']);
    Route::get('/api/admin/check-updates', [AdminController::class, 'checkUpdates']);
    Route::get('/api/recepcja/check-updates', [ReceptionController::class, 'checkUpdates']);
});
