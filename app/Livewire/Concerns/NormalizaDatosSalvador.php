<?php

namespace App\Livewire\Concerns;

use App\Support\DatosSalvador;

trait NormalizaDatosSalvador
{
    protected function normalizarCampos(array $campos): void
    {
        foreach ($campos as $campo) {
            $this->$campo = DatosSalvador::campo($campo, $this->$campo);
        }
    }
}
