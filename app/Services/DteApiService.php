<?php

namespace App\Services;

use App\Models\ConfiguracionDTE;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class DteApiService
{
    private $config;
    private $baseUrl;

    public function __construct()
    {
        $this->config = ConfiguracionDTE::first();
        if (!$this->config) {
            throw new Exception("No hay configuración DTE registrada.");
        }

        // URLs del MH según ambiente (00 = Pruebas, 01 = Producción)
        $this->baseUrl = $this->config->ambiente === '01' 
            ? 'https://api.mh.gob.sv/fesv/recepciondte' // Producción
            : 'https://apitest.mh.gob.sv/fesv/recepciondte'; // Pruebas
    }

    /**
     * Autentica con el MH y retorna el token JWT.
     * Guarda el token en caché por 24 horas (lo que dura según MH).
     */
    public function autenticar()
    {
        return Cache::remember('mh_jwt_token', 60 * 23, function () {
            $url = str_replace('/recepciondte', '/seguridad/auth', $this->baseUrl);
            
            $response = Http::asForm()->post($url, [
                'user' => $this->config->nit,
                'pwd' => $this->config->password_api,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] == 'OK' && isset($data['body']['token'])) {
                    return $data['body']['token'];
                }
            }

            throw new Exception("Error de autenticación con Ministerio de Hacienda: " . $response->body());
        });
    }

    /**
     * Transmite un DTE firmado en tiempo real al MH.
     */
    public function transmitirDTE($jsonFirmado, $tipoDocumento, $codigoGeneracion)
    {
        $token = $this->autenticar();

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/JSON'
        ])->post($this->baseUrl . '/', [
            'ambiente' => $this->config->ambiente,
            'idEnvio' => uniqid(),
            'version' => 1,
            'tipoDte' => $tipoDocumento,
            'documento' => $jsonFirmado['body'] // El firmador devuelve el base64 aquí a veces, o se manda el JSON integro
            // La estructura real depende del firmador, asumimos que $jsonFirmado tiene la estructura correcta
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception("Error al transmitir DTE: " . $response->body());
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
}
