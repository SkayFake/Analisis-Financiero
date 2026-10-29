<?php

namespace App\Services;

use App\Models\ConfiguracionDTE;
use Illuminate\Support\Facades\Http;
use Exception;
use App\Exceptions\DteConfigurationException;

class FirmadorService
{
    private $config;

    public function __construct()
    {
        $this->config = ConfiguracionDTE::first();
    }

    public function firmarDocumento(array $jsonEstructural)
    {
        if (!config('erp.dte.enabled')) {
            throw new DteConfigurationException('La integración con Hacienda está deshabilitada. Utilice documentos locales.');
        }
        if (!$this->config || !filter_var($this->config->url_firmador, FILTER_VALIDATE_URL)
            || !in_array(parse_url($this->config->url_firmador, PHP_URL_SCHEME), ['http', 'https'])
            || empty($this->config->nit) || empty($this->config->password_firmador)) {
            throw new DteConfigurationException('Configure el firmador y sus credenciales antes de emitir documentos fiscales.');
        }

        try {
            $response = Http::connectTimeout(10)->timeout(30)->post(rtrim($this->config->url_firmador, '/') . '/firmardocumento', [
                'nit' => $this->config->nit,
                'activo' => true,
                'passwordPri' => $this->config->password_firmador,
                'dteJson' => $jsonEstructural
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? null) === 'OK' && is_string($data['body'] ?? null) && trim($data['body']) !== '') {
                    return $data['body']; // Retorna el string o json firmado real
                }
                throw new Exception('El firmador no devolvió un documento firmado válido.');
            }

            throw new Exception("Error de comunicación con el Firmador: " . $response->status());

        } catch (\Exception $e) {
            throw new Exception('No se pudo obtener una firma válida. Verifique el servicio firmador.', 0, $e);
        }
    }
}
