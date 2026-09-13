<?php

namespace App\Services;

use App\Models\ConfiguracionDTE;
use Illuminate\Support\Facades\Http;
use Exception;

class FirmadorService
{
    private $config;

    public function __construct()
    {
        $this->config = ConfiguracionDTE::first();
        if (!$this->config || !$this->config->url_firmador) {
            throw new Exception("No hay URL del firmador configurada.");
        }
    }

    /**
     * Envía el JSON estructural al firmador local/remoto y retorna el JSON Firmado.
     */
    public function firmarDocumento(array $jsonEstructural)
    {
        try {
            $response = Http::post($this->config->url_firmador . '/firmardocumento', [
                'nit' => $this->config->nit,
                'activo' => true,
                'passwordPri' => $this->config->password_firmador,
                'dteJson' => $jsonEstructural
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'OK') {
                    return $data['body']; // Retorna el string o json firmado real
                }
                throw new Exception("El firmador respondió con error: " . json_encode($data));
            }

            throw new Exception("Error de comunicación con el Firmador: " . $response->status());

        } catch (\Exception $e) {
            throw new Exception("No se pudo conectar al Firmador en {$this->config->url_firmador}. Verifique que el servicio esté corriendo. Detalles: " . $e->getMessage());
        }
    }
}
