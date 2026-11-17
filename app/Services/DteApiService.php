<?php

namespace App\Services;

use App\Exceptions\DteConfigurationException;

/** No hay transmisión fiscal hasta completar esquemas, eventos y pruebas de autorización MH. */
class DteApiService
{
    private function pendiente(): never
    {
        throw new DteConfigurationException('Transmisión fiscal bloqueada: falta integrar y validar los esquemas y eventos vigentes de Hacienda.');
    }

    public function autenticar()
    {
        $this->pendiente();
    }

    public function transmitirDTE($jsonFirmado, $tipoDocumento, $codigoGeneracion)
    {
        $this->pendiente();
    }

    public function transmitirLoteContingencia($jsonLoteFirmado)
    {
        $this->pendiente();
    }

    public function transmitirInvalidacion($jsonInvalidacionFirmado)
    {
        $this->pendiente();
    }
}
