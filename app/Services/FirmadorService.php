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
        // If config is missing, create a dummy one in memory so we don't crash
        if (!$this->config) {
            $this->config = new ConfiguracionDTE([
                'url_firmador' => 'dummy',
                'nit' => '0000-000000-000-0'
            ]);
        }
    }

    public function firmarDocumento(array $jsonEstructural)
    {
        if ($this->config->url_firmador === 'dummy' || empty($this->config->url_firmador)) {
            // Bypass para pruebas: retorna un JSON dummy
            return json_encode([
                'status' => 'OK',
                'body' => '{"firma": "DUMMY_SIGNATURE_FOR_TESTING"}'
            ]);
        }

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
