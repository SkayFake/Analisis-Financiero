<?php

namespace App\Policies;

use App\Models\Credito;
use App\Models\User;

class CreditoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('creditos.view') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Credito $credito): bool
    {
        return $user->can('creditos.view') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('creditos.create') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Credito $credito): bool
    {
        return ($user->can('creditos.approve') || $user->hasRole('super-admin'))
            && $credito->estado === 'pendiente_aprobacion';
    }

    /**
     * Determine whether the user can reject the model.
     */
    public function reject(User $user, Credito $credito): bool
    {
        return ($user->can('creditos.approve') || $user->can('creditos.manage') || $user->hasRole('super-admin'))
            && $credito->estado === 'pendiente_aprobacion';
    }

    /**
     * Determine whether the user can process payment on the model.
     */
    public function processPayment(User $user, Credito $credito): bool
    {
        return ($user->can('cobros.process') || $user->hasRole('super-admin'))
            && in_array($credito->estado, ['vigente', 'vencido']);
    }
}
