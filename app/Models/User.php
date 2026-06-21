<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // Ustawienia bazy danych
    protected $table = 'Uzytkownicy';
    public $timestamps = false;
    // -----------------------------------------

    protected $fillable = [
        'login',
        'haslo',
        'rola',
    ];

    protected $hidden = [
        'haslo',
    ];
}
