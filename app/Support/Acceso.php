<?php

namespace App\Support;

final class Acceso
{
    public static function exigir(string $permiso): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('super-admin') || $user->can($permiso)), 403);
    }
}
