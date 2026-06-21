<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // <-- Zmiana: Używamy teraz modelu User

class AuthController
{
    public function showLogin() {
        return view('auth.login');
    }

    public function login(Request $request) {
        $credentials = $request->validate([
            'login' => 'required|string',
            'haslo' => 'required|string',
        ]);

        // Używamy modelu User zamiast DB::table
        $user = User::where('login', $credentials['login'])
            ->where('haslo', $credentials['haslo'])
            ->first();

        if ($user) {
            // Laravelowe logowanie z użyciem obiektu
            Auth::login($user);

            return match($user->rola) {
                'Recepcja' => redirect('/recepcja'),
                'Admin' => redirect('/admin'),
                'Technik' => redirect('/technik'),
                'Magazyn' => redirect('/magazyn'),
                default => redirect('/login')->withErrors(['auth' => 'Brak przypisanej roli.']),
            };
        }

        return back()->withErrors(['auth' => 'Nieprawidłowy login lub hasło!']);
    }

    public function logout() {
        Auth::logout();
        return redirect('/login');
    }
}
