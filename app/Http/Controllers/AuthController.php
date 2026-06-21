<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    public function showLogin() {
        return view('auth.login');
    }

    public function login(Request $request) {
        $dane = $request->validate([
            'login' => 'required|string',
            'haslo' => 'required|string',
        ]);

        // Auth::attempt weryfikuje hasło przez Hash::check (kolumna "haslo" wskazana
        // w modelu User przez getAuthPassword). Klucz "password" jest wymagany przez
        // provider Eloquent i jest automatycznie pomijany przy budowaniu zapytania.
        $credentials = [
            'login' => $dane['login'],
            'password' => $dane['haslo'],
        ];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return match(Auth::user()->rola) {
                'Recepcja' => redirect('/recepcja'),
                'Admin' => redirect('/admin'),
                'Technik' => redirect('/technik'),
                'Magazyn' => redirect('/magazyn'),
                default => redirect('/login')->withErrors(['auth' => 'Brak przypisanej roli.']),
            };
        }

        return back()->withErrors(['auth' => 'Nieprawidłowy login lub hasło!']);
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
