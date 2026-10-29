<?php

namespace App\Services;

use App\Models\ConfiguracionDTE;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use App\Exceptions\DteConfigurationException;

class DteApiService
{
    private $config;
    private $baseUrl;

    public function __construct()
    {
        $this->config = ConfiguracionDTE::first();

        // URLs del MH según ambiente (00 = Pruebas, 01 = Producción)
        $this->baseUrl = $this->config?->ambiente === '01'
            ? 'https://api.mh.gob.sv/fesv/recepciondte' // Producción
            : 'https://apitest.mh.gob.sv/fesv/recepciondte'; // Pruebas
    }

    /**
     * Autentica con el MH y retorna el token JWT.
     * Guarda el token en caché por 24 horas (lo que dura según MH).
     */
    public function autenticar()
    {
        $this->validarConfiguracion();

        $cacheKey = 'mh_jwt_token:'.hash('sha256', $this->config->id.'|'.$this->config->ambiente.'|'.$this->config->nit.'|'.$this->config->password_api);
        return Cache::remember($cacheKey, 60 * 60 * 23, function () {
            $url = str_replace('/recepciondte', '/seguridad/auth', $this->baseUrl);
            
            $response = Http::connectTimeout(10)->timeout(30)->asForm()->post($url, [
                'user' => $this->config->nit,
                'pwd' => $this->config->password_api,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] == 'OK' && isset($data['body']['token'])) {
                    return $data['body']['token'];
                }
            }

            throw new Exception('Error de autenticación con Ministerio de Hacienda. HTTP '.$response->status());
        });
    }

    /**
     * Transmite un DTE firmado en tiempo real al MH.
     */
    public function transmitirDTE($jsonFirmado, $tipoDocumento, $codigoGeneracion)
    {
        if (!is_string($jsonFirmado) || trim($jsonFirmado) === '') {
            throw new \InvalidArgumentException('El firmador debe devolver un documento firmado no vacío.');
        }

        $token = $this->autenticar();

        $response = Http::connectTimeout(10)->timeout(30)->withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/JSON'
        ])->post($this->baseUrl . '/', [
            'ambiente' => $this->config->ambiente,
            'idEnvio' => uniqid(),
            'version' => 1,
            'tipoDte' => $tipoDocumento,
            'documento' => $jsonFirmado,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Error al transmitir DTE. HTTP '.$response->status());
    }

    /**
     * Transmite un Lote de Contingencia
     */
    public function transmitirLoteContingencia($jsonLoteFirmado)
    {
        $token = $this->autenticar();

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/JSON'
        ])->post($this->baseUrl . '/lote', $jsonLoteFirmado);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception("Error al transmitir Lote de Contingencia: " . $response->body());
    }

    /**
     * Transmite la anulación (Invalidación) de un DTE
     */
    public function transmitirInvalidacion($jsonInvalidacionFirmado)
    {
        $token = $this->autenticar();

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/JSON'
        ])->post($this->baseUrl . '/anuladte', $jsonInvalidacionFirmado);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception("Error al transmitir Invalidación: " . $response->body());
    }

    private function validarConfiguracion(): void
    {
        if (!config('erp.dte.enabled')) {
            throw new DteConfigurationException('La integración con Hacienda está deshabilitada. Utilice documentos locales.');
        }
        if (!$this->config || !in_array($this->config->ambiente, ['00', '01'])
            || empty($this->config->nit) || empty($this->config->password_api)
            || $this->config->password_api === 'dummy') {
            throw new DteConfigurationException('Configure las credenciales y el ambiente DTE antes de emitir documentos fiscales.');
        }
    }
}
