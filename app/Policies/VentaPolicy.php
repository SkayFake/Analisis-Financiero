<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->can('facturacion.view') || $user->can('facturacion.emitir');
    }

    public function view(User $user, Venta $venta): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->can('facturacion.emitir');
    }
}
