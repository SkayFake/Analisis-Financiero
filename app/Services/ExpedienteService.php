<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\ExpedienteDocumento;
use App\Models\ExpedienteEvento;
use App\Models\Fiador;
use App\Models\User;
use App\Rules\DuiSalvador;
use App\Rules\NitSalvador;
use App\Rules\TelefonoSalvador;
use App\Support\DatosSalvador;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpedienteService
{
    public function politica(Cliente $cliente): array
    {
        $resultado = ['tipo_cliente' => $cliente->tipo];
        foreach (['cliente' => $cliente->tipo, 'credito' => 'credito', 'fiador' => 'fiador'] as $ambito => $tipo) {
            foreach (config("expedientes.requisitos.$tipo", []) as $grupo) {
                $resultado[$ambito][$grupo] = config("expedientes.grupos.$grupo");
            }
        }

        return $resultado;
    }

    public function permitir(User $usuario, string $accion): void
    {
        abort_unless($usuario->can('expedientes.view') && $usuario->can("expedientes.$accion"), 403);
    }

    public function contexto(Cliente $cliente, ?Credito $credito = null, ?Fiador $fiador = null): void
    {
        abort_if($credito && (int) $credito->cliente_id !== $cliente->id, 404);
        abort_if($fiador && (! $credito || (int) $fiador->credito_id !== $credito->id), 404);
    }

    private function bloquear(Cliente $cliente, ?Credito $credito, ?Fiador $fiador): array
    {
        $cliente = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
        $credito = $credito ? Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail() : null;
        $fiador = $fiador ? Fiador::whereKey($fiador->id)->lockForUpdate()->firstOrFail() : null;
        $this->contexto($cliente, $credito, $fiador);

        return [$cliente, $credito, $fiador];
    }

    public function identidad(Cliente $cliente, ?Fiador $fiador = null): array
    {
        $titular = $fiador ?? $cliente;
        $dui = $fiador ? $fiador->dui : ($cliente->tipo === 'juridica' ? $cliente->datosJuridicos?->dui_representante : $cliente->dui);
        $nombre = $fiador ? $fiador->nombre : ($cliente->tipo === 'juridica' ? $cliente->datosJuridicos?->representante_legal : $cliente->nombre);

        return [
            'titular' => $nombre ?: $titular->nombre,
            'huella' => hash('sha256', json_encode([$cliente->tipo, $nombre, $dui, $titular->nit], JSON_UNESCAPED_UNICODE)),
            'dui_valido' => DatosSalvador::duiValido($dui),
        ];
    }

    private function huellaDocumento(Cliente $cliente, ?Fiador $fiador, string $categoria): string
    {
        $titular = $fiador ?? $cliente;
        $datos = [];
        if ($categoria === 'domicilio') {
            $datos = $titular->only($fiador ? ['direccion'] : ['direccion', 'departamento', 'municipio']);
        }
        if (in_array($categoria, ['constancia_salario', 'boletas_pago', 'estado_cuenta', 'declaracion_renta', 'estados_financieros'])) {
            $datos = $titular->only(['ingresos', 'egresos', 'lugar_trabajo']);
            if (! $fiador && $cliente->tipo === 'juridica') {
                $datos['financieros'] = $cliente->datosJuridicos?->only(['balance_general', 'estado_resultados', 'fecha_balance']);
            }
        }

        return hash('sha256', $this->identidad($cliente, $fiador)['huella'].json_encode($datos, JSON_UNESCAPED_UNICODE));
    }

    public function categorias(string $ambito): array
    {
        $todas = config('expedientes.categorias');
        $credito = ['solicitud', 'contrato'];
        $juridicas = ['constitucion', 'representacion', 'nrc'];

        return match ($ambito) {
            'credito' => array_intersect_key($todas, array_flip([...$credito, 'otro', 'consentimiento', 'autorizacion_consulta'])),
            'fiador' => array_diff_key($todas, array_flip([...$credito, ...$juridicas])),
            default => array_diff_key($todas, array_flip([...$credito, 'fianza'])),
        };
    }

    public function documentos(Cliente $cliente, ?Credito $credito = null, ?Fiador $fiador = null)
    {
        return ExpedienteDocumento::where('cliente_id', $cliente->id)
            ->where('credito_id', $credito?->id)->where('fiador_id', $fiador?->id);
    }

    public function subir(Cliente $cliente, ?Credito $credito, ?Fiador $fiador, UploadedFile $archivo, array $datos, User $usuario): ExpedienteDocumento
    {
        $this->permitir($usuario, 'upload');
        $this->contexto($cliente, $credito, $fiador);
        $ambito = $fiador ? 'fiador' : ($credito ? 'credito' : 'cliente');
        $datos = Validator::make($datos, [
            'categoria' => ['required', Rule::in(array_keys($this->categorias($ambito)))],
            'fecha_emision' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d', Rule::when(! empty($datos['fecha_emision']), 'after_or_equal:fecha_emision')],
            'notas' => 'nullable|string|max:2000',
            'reemplaza_id' => 'nullable|integer|min:1',
            'motivo' => 'required_with:reemplaza_id|nullable|string|min:8|max:2000',
        ])->validate();
        $mime = $archivo->getMimeType();
        $extensiones = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! $archivo->isValid() || $archivo->getSize() <= 0 || $archivo->getSize() > config('expedientes.max_kb') * 1024
            || ! in_array($extension, $extensiones[$mime] ?? [], true)) {
            throw ValidationException::withMessages(['archivo' => 'Adjunte un PDF, JPG, PNG o WebP de hasta '.config('expedientes.max_kb').' KB; la extensión debe coincidir con el contenido.']);
        }
        if ($mime === 'application/pdf') {
            $cabecera = file_get_contents($archivo->getRealPath(), false, null, 0, 5);
            $cola = file_get_contents($archivo->getRealPath(), false, null, max(0, $archivo->getSize() - 2048));
            if ($cabecera !== '%PDF-' || ! str_contains($cola, '%%EOF')) {
                throw ValidationException::withMessages(['archivo' => 'El PDF está incompleto o no tiene una estructura reconocible.']);
            }
        } else {
            $imagen = @getimagesize($archivo->getRealPath());
            if (! $imagen || $imagen[0] > 10000 || $imagen[1] > 10000 || $imagen[0] * $imagen[1] > 25000000) {
                throw ValidationException::withMessages(['archivo' => 'La imagen es inválida o supera 25 megapíxeles / 10000 píxeles por lado.']);
            }
        }
        $ruta = null;
        try {
            return DB::transaction(function () use ($cliente, $credito, $fiador, $archivo, $datos, $usuario, $mime, $extensiones, &$ruta) {
                [$cliente, $credito, $fiador] = $this->bloquear($cliente, $credito, $fiador);
                if (ExpedienteDocumento::where('cliente_id', $cliente->id)->count() >= config('expedientes.max_documentos_por_cliente')) {
                    throw ValidationException::withMessages(['archivo' => 'Se alcanzó el límite documental del expediente. Solicite revisión al administrador.']);
                }
                $anterior = null;
                if (! empty($datos['reemplaza_id'])) {
                    $anterior = $this->documentos($cliente, $credito, $fiador)->whereKey($datos['reemplaza_id'])->lockForUpdate()->firstOrFail();
                    if (! $anterior->vigente || $anterior->categoria !== $datos['categoria']) {
                        throw ValidationException::withMessages(['reemplaza_id' => 'Solo se reemplaza la versión actual y de la misma categoría.']);
                    }
                }
                $ruta = $cliente->id.'/'.Str::uuid().'.'.$extensiones[$mime][0];
                $contenido = file_get_contents($archivo->getRealPath());
                if (! Storage::disk('expedientes')->put($ruta, $contenido)) {
                    throw new \RuntimeException('No se pudo guardar el comprobante.');
                }
                $identidad = $this->identidad($cliente, $fiador);
                $documento = ExpedienteDocumento::create([
                    'cliente_id' => $cliente->id, 'credito_id' => $credito?->id, 'fiador_id' => $fiador?->id,
                    'categoria' => $datos['categoria'], 'grupo' => $anterior?->grupo ?? (string) Str::uuid(), 'version' => ($anterior?->version ?? 0) + 1,
                    'reemplaza_id' => $anterior?->id, 'vigente' => true,
                    'nombre_original' => mb_substr(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '_', $archivo->getClientOriginalName()), 0, 180),
                    'ruta' => $ruta, 'mime' => $mime, 'bytes' => strlen($contenido), 'sha256' => hash('sha256', $contenido),
                    'titular' => $identidad['titular'], 'identidad_huella' => $this->huellaDocumento($cliente, $fiador, $datos['categoria']),
                    'fecha_emision' => $datos['fecha_emision'] ?? null, 'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                    'notas' => $datos['notas'] ?? null, 'estado' => 'pendiente', 'subido_por' => $usuario->id,
                ]);
                $anterior?->update(['vigente' => false]);
                $this->evento($cliente, $credito, $fiador, $documento, $anterior ? 'reemplazo' : 'presentacion', $anterior?->only(['id', 'version', 'sha256']), $documento->toArray(), $datos['motivo'] ?? 'Comprobante presentado; pendiente de revisión.', $usuario);

                return $documento;
            });
        } catch (\Throwable $e) {
            if ($ruta) {
                Storage::disk('expedientes')->delete($ruta);
            }
            throw $e;
        }
    }

    public function integro(ExpedienteDocumento $documento): bool
    {
        $disk = Storage::disk('expedientes');

        return $disk->exists($documento->ruta) && hash_equals($documento->sha256, hash_file('sha256', $disk->path($documento->ruta)));
    }

    public function revisar(ExpedienteDocumento $documento, string $estado, string $motivo, User $usuario): void
    {
        $this->permitir($usuario, 'review');
        Validator::make(compact('estado', 'motivo'), ['estado' => ['required', Rule::in(['validado', 'rechazado', 'anulado'])], 'motivo' => 'required|string|min:8|max:2000'])->validate();
        DB::transaction(function () use ($documento, $estado, $motivo, $usuario) {
            [$cliente, $credito, $fiador] = $this->bloquear($documento->cliente, $documento->credito, $documento->fiador);
            $documento = ExpedienteDocumento::whereKey($documento->id)->lockForUpdate()->firstOrFail();
            if (! $documento->vigente || $documento->estado === 'anulado') {
                throw ValidationException::withMessages(['estado' => 'Una versión sustituida o anulada conserva su revisión histórica; presente una nueva versión.']);
            }
            if ($estado === 'validado' && (! $this->integro($documento) || $documento->fecha_vencimiento?->lt(today())
                || ! hash_equals($documento->identidad_huella, $this->huellaDocumento($cliente, $fiador, $documento->categoria)))) {
                throw ValidationException::withMessages(['estado' => 'No se puede validar: archivo ausente/alterado, vencido o datos del titular cambiados. Presente una nueva versión cuando corresponda.']);
            }
            $antes = $documento->only(['estado', 'revisado_por', 'revisado_at', 'motivo_revision', 'identidad_revisada']);
            $documento->update(['estado' => $estado, 'revisado_por' => $usuario->id, 'revisado_at' => now(), 'motivo_revision' => $motivo, 'identidad_revisada' => $estado === 'validado' ? $this->huellaDocumento($cliente, $fiador, $documento->categoria) : null]);
            $this->evento($cliente, $credito, $fiador, $documento, 'revision', $antes, $documento->only(array_keys($antes)), $motivo, $usuario);
        });
    }

    public function utilizable(ExpedienteDocumento $documento, Cliente $cliente, ?Fiador $fiador = null): bool
    {
        return $documento->vigente && $documento->estado === 'validado'
            && (! $documento->fecha_vencimiento || $documento->fecha_vencimiento->gte(today()))
            && $documento->identidad_revisada === $this->huellaDocumento($cliente, $fiador, $documento->categoria)
            && $this->integro($documento);
    }

    public function lista(Credito $credito): array
    {
        $cliente = $credito->cliente;
        $politica = $credito->requisitos_expediente ?? $this->politica($cliente);
        $contextos = [['ambito' => 'cliente', 'titulo' => 'Cliente: '.$cliente->nombre, 'credito' => null, 'fiador' => null],
            ['ambito' => 'credito', 'titulo' => 'Crédito: '.$credito->numero, 'credito' => $credito, 'fiador' => null]];
        foreach ($credito->fiadores()->get() as $fiador) {
            $contextos[] = ['ambito' => 'fiador', 'titulo' => 'Fiador: '.$fiador->nombre, 'credito' => $credito, 'fiador' => $fiador];
        }
        $lista = [];
        foreach ($contextos as $contexto) {
            $documentos = $this->documentos($cliente, $contexto['credito'], $contexto['fiador'])->get()
                ->filter(fn ($d) => $this->utilizable($d, $cliente, $contexto['fiador']));
            foreach ($politica[$contexto['ambito']] ?? [] as $clave => $grupo) {
                $aceptados = [];
                foreach ($grupo['alternativas'] ?? [] as $alternativa) {
                    $elegidos = [];
                    foreach ($alternativa as $categoria) {
                        $doc = $documentos->firstWhere('categoria', $categoria);
                        if (! $doc) {
                            $elegidos = [];
                            break;
                        }
                        $elegidos[] = $doc->only(['id', 'categoria', 'version', 'sha256', 'titular', 'revisado_por', 'revisado_at', 'fecha_vencimiento']);
                    }
                    if ($elegidos) {
                        $aceptados = $elegidos;
                        break;
                    }
                }
                if ($clave === 'identidad' && ! $this->identidad($cliente, $contexto['fiador'])['dui_valido']) {
                    $aceptados = [];
                }
                if ($clave === 'nit' && ! DatosSalvador::nitValido($cliente->nit, false)) {
                    $aceptados = [];
                }
                $lista[] = ['titulo' => $contexto['titulo'], 'requisito' => $grupo['nombre'] ?? $clave, 'completo' => (bool) $aceptados, 'documentos' => $aceptados];
            }
            if ($contexto['fiador']) {
                $f = $contexto['fiador'];
                if (($cliente->tipo === 'natural' && preg_replace('/\D/', '', $f->dui ?? '') === preg_replace('/\D/', '', $cliente->dui ?? ''))
                    || $credito->fiadores()->whereRaw("REPLACE(dui, '-', '') = ?", [preg_replace('/\D/', '', $f->dui ?? '')])->where('id', '!=', $f->id)->exists()) {
                    $lista[] = ['titulo' => $contexto['titulo'], 'requisito' => 'Fiador distinto del cliente y sin duplicados en el crédito', 'completo' => false, 'documentos' => []];
                }
                $lista[] = ['titulo' => $contexto['titulo'], 'requisito' => 'Dirección, teléfono e ingresos/egresos del fiador completos; ingresos mayores a egresos (política interna)',
                    'completo' => (bool) ($f->direccion && Validator::make(['telefono' => $f->telefono], ['telefono' => ['required', new TelefonoSalvador]])->passes() && $f->ingresos > $f->egresos && $f->egresos >= 0), 'documentos' => []];
            }
        }

        return $lista;
    }

    public function faltantes(Credito $credito): array
    {
        if (isset($credito->requisitos_expediente['tipo_cliente']) && $credito->requisitos_expediente['tipo_cliente'] !== $credito->cliente->tipo) {
            return ['El tipo de cliente cambió desde la solicitud. Registre una nueva solicitud con los requisitos correspondientes.'];
        }

        return collect($this->lista($credito))->where('completo', false)->map(fn ($r) => $r['titulo'].' — '.$r['requisito'])->values()->all();
    }

    public function guardarFiador(Credito $credito, ?Fiador $fiador, array $datos, User $usuario): Fiador
    {
        $this->permitir($usuario, 'manage');
        $datos = DatosSalvador::normalizar($datos);
        $datos = Validator::make($datos, [
            'nombre' => 'required|string|max:150|regex:/[\p{L}]/u',
            'dui' => ['required', new DuiSalvador], 'nit' => ['nullable', new NitSalvador],
            'telefono' => ['required', new TelefonoSalvador], 'direccion' => 'required|string|max:250',
            'lugar_trabajo' => 'nullable|string|max:255', 'ingresos' => 'required|numeric|min:0|max:999999999999.99|decimal:0,2',
            'egresos' => 'required|numeric|min:0|max:999999999999.99|decimal:0,2',
            'observaciones' => 'nullable|string|max:2000', 'motivo' => 'required|string|min:8|max:2000',
        ])->validate();

        return DB::transaction(function () use ($credito, $fiador, $datos, $usuario) {
            [$cliente, $credito, $fiador] = $this->bloquear($credito->cliente, $credito, $fiador);
            if ($credito->estado !== 'pendiente_aprobacion') {
                throw ValidationException::withMessages(['fiador' => 'Los fiadores solo se modifican durante la solicitud pendiente.']);
            }
            if ($cliente->tipo === 'natural' && preg_replace('/\D/', '', $datos['dui']) === preg_replace('/\D/', '', $cliente->dui ?? '')) {
                throw ValidationException::withMessages(['dui' => 'El cliente no puede ser su propio fiador.']);
            }
            if ($credito->fiadores()->whereRaw("REPLACE(dui, '-', '') = ?", [preg_replace('/\D/', '', $datos['dui'])])->when($fiador, fn ($q) => $q->where('id', '!=', $fiador->id))->exists()) {
                throw ValidationException::withMessages(['dui' => 'Este fiador ya está registrado en el crédito.']);
            }
            if (! empty($datos['nit']) && strlen(preg_replace('/\D/', '', $datos['nit'])) === 9 && $datos['nit'] !== $datos['dui']) {
                throw ValidationException::withMessages(['nit' => 'El NIT homologado debe coincidir con el DUI del fiador.']);
            }
            $antes = $fiador?->toArray();
            $motivo = $datos['motivo'];
            unset($datos['motivo']);
            if ($fiador) {
                $fiador->update($datos);
            } else {
                $fiador = $credito->fiadores()->create($datos);
            }
            $this->evento($cliente, $credito, $fiador, null, 'datos_fiador', $antes, $fiador->toArray(), $motivo, $usuario);

            return $fiador;
        });
    }

    public function evento(Cliente $cliente, ?Credito $credito, ?Fiador $fiador, ?ExpedienteDocumento $documento, string $accion, ?array $antes, ?array $despues, string $motivo, ?User $usuario): ExpedienteEvento
    {
        return ExpedienteEvento::create(['cliente_id' => $cliente->id, 'credito_id' => $credito?->id, 'fiador_id' => $fiador?->id, 'documento_id' => $documento?->id,
            'accion' => $accion, 'antes' => $antes, 'despues' => $despues, 'motivo' => $motivo,
            'usuario_id' => $usuario?->id, 'usuario_nombre' => $usuario?->name ?? 'Sistema', 'created_at' => now()]);
    }
}
