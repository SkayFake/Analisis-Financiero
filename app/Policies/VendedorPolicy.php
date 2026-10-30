<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendedor;

class VendedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendedores.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Vendedor $vendedor): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->can('vendedores.manage') || $user->hasRole('super-admin');
    }

    public function metas(User $user): bool
    {
        return $user->can('vendedores.metas') || $user->hasRole('super-admin');
    }

    public function assign(User $user): bool
    {
        return $user->can('vendedores.assign') || $user->hasRole('super-admin');
    }

    public function gestionar(User $user): bool
    {
        return $user->can('vendedores.gestionar') || $user->hasRole('super-admin');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user) && ($user->can('vendedores.export') || $user->hasRole('super-admin'));
    }

    public function audit(User $user): bool
    {
        return $this->viewAny($user) && ($user->can('vendedores.audit') || $user->hasRole('super-admin'));
    }
}
