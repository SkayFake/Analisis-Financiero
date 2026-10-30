<?php

namespace App\Services;

use App\Models\Cartera;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\GestionCobro;
use App\Models\Pago;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendedorAuditoria;
use App\Models\VendedorMeta;
use App\Models\Venta;
use App\Rules\TelefonoSalvador;
use App\Support\DatosSalvador;
use App\Support\ReglasFormulario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendedorService
{
    public function auditar(Vendedor $vendedor, string $accion, string $entidad, int $id, ?array $antes, ?array $despues, string $motivo, ?User $actor): void
    {
        VendedorAuditoria::create([
            'vendedor_id' => $vendedor->id, 'accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $id,
            'antes' => $antes, 'despues' => $despues, 'motivo' => $motivo,
            'usuario_id' => $actor?->id, 'usuario_nombre' => $actor?->name ?? 'Proceso del sistema', 'created_at' => now(),
        ]);
    }

    public function guardar(?int $id, array $datos, string $motivo, User $actor): Vendedor
    {
        $datos = DatosSalvador::normalizar($datos);
        $datos = Validator::make($datos, [
            'codigo' => ['required', ...explode('|', ReglasFormulario::CODIGO), 'max:20', Rule::unique('vendedores', 'codigo')->ignore($id)],
            'nombre' => 'required|string|max:255|regex:/[\p{L}]/u', 'telefono' => ['nullable', 'string', new TelefonoSalvador],
            'email' => 'nullable|string|email:rfc|max:255', 'activo' => 'required|boolean',
            'zona_id' => ['nullable', 'integer', Rule::exists('zonas', 'id')->where('activa', true)],
        ])->validate();
        $motivo = $this->motivo($motivo);

        return DB::transaction(function () use ($id, $datos, $motivo, $actor) {
            $vendedor = $id ? Vendedor::whereKey($id)->lockForUpdate()->firstOrFail() : new Vendedor;
            $antes = $id ? $vendedor->only(['codigo', 'nombre', 'telefono', 'email', 'zona_id', 'activo']) : null;
            $vendedor->fill($datos)->save();
            $this->auditar($vendedor, $id ? 'edicion' : 'alta', 'vendedores', $vendedor->id, $antes,
                $vendedor->only(['codigo', 'nombre', 'telefono', 'email', 'zona_id', 'activo']), $motivo, $actor);

            return $vendedor;
        });
    }

    public function meta(Vendedor $vendedor, string $mes, array $datos, string $motivo, User $actor): VendedorMeta
    {
        $periodo = app(VendedorMetricasService::class)->periodo($mes);
        $valores = Validator::make($datos, ['meta_ventas' => 'required|'.ReglasFormulario::DINERO, 'meta_cobros' => 'required|'.ReglasFormulario::DINERO])->validate();
        $motivo = $this->motivo($motivo);

        return DB::transaction(function () use ($vendedor, $periodo, $valores, $motivo, $actor) {
            $vendedor = Vendedor::whereKey($vendedor->id)->lockForUpdate()->firstOrFail();
            $meta = VendedorMeta::firstOrNew(['vendedor_id' => $vendedor->id, 'periodo' => $periodo->toDateString()]);
            $antes = $meta->exists ? $meta->only(['periodo', 'meta_ventas', 'meta_cobros']) : null;
            $meta->fill($valores)->save();
            if ($periodo->isSameMonth(today())) {
                $vendedor->update(['meta_mensual' => $valores['meta_ventas']]);
            }
            $this->auditar($vendedor, 'meta_mensual', 'vendedor_metas', $meta->id, $antes,
                ['periodo' => $periodo->format('Y-m')] + $valores, $motivo, $actor);

            return $meta;
        });
    }

    public function reasignarCredito(Credito $credito, Vendedor $destino, ?int $carteraId, string $motivo, User $actor): void
    {
        $motivo = $this->motivo($motivo);
        DB::transaction(function () use ($credito, $destino, $carteraId, $motivo, $actor) {
            $credito = Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail();
            $destino = Vendedor::whereKey($destino->id)->sharedLock()->firstOrFail();
            if (! $destino->activo || ! in_array($credito->estado, ['vigente', 'vencido', 'incobrable', 'pendiente_aprobacion', 'aprobado'])) {
                throw ValidationException::withMessages(['vendedor_id' => 'El destino debe estar activo y el crédito debe estar abierto.']);
            }
            if ($credito->vendedor_id === $destino->id) {
                throw ValidationException::withMessages(['vendedor_id' => 'El crédito ya está asignado a ese vendedor.']);
            }
            if ($carteraId !== null && ! Cartera::whereKey($carteraId)->where('activa', true)->where('vendedor_id', $destino->id)->exists()) {
                throw ValidationException::withMessages(['cartera_id' => 'La cartera debe estar activa y pertenecer al vendedor de destino.']);
            }
            $anterior = $credito->vendedor;
            $antes = $credito->only(['vendedor_id', 'cartera_id']);
            $credito->update(['vendedor_id' => $destino->id, 'cartera_id' => $carteraId]);
            $despues = $credito->only(['vendedor_id', 'cartera_id']);
            foreach (collect([$anterior, $destino])->filter()->unique('id') as $vendedor) {
                $this->auditar($vendedor, 'reasignacion_credito', 'creditos', $credito->id, $antes, $despues, $motivo, $actor);
            }
        });
    }

    public function registrarGestion(Credito $credito, array $datos, User $actor): GestionCobro
    {
        if (isset($datos['notas']) && is_string($datos['notas'])) {
            $datos['notas'] = trim($datos['notas']);
        }
        $datos = Validator::make($datos, [
            'medio' => 'required|string|in:telefono,visita,mensaje,correo,oficina',
            'resultado' => 'required|string|in:sin_respuesta,contactado,promesa_pago,se_niega,datos_incorrectos',
            'notas' => 'required|string|min:10|max:2000',
            'fecha_compromiso' => 'nullable|required_if:resultado,promesa_pago|prohibited_unless:resultado,promesa_pago|date_format:Y-m-d|after_or_equal:today',
            'monto_compromiso' => 'nullable|required_if:resultado,promesa_pago|prohibited_unless:resultado,promesa_pago|numeric|min:0.01|max:999999999999.99|decimal:0,2',
        ])->validate();

        return DB::transaction(function () use ($credito, $datos, $actor) {
            $credito = Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail();
            if (! $credito->vendedor_id || ! in_array($credito->estado, ['vigente', 'vencido', 'incobrable'])) {
                throw ValidationException::withMessages(['gestion' => 'Debe asignar un responsable y seleccionar un crédito con deuda activa.']);
            }
            if ((float) ($datos['monto_compromiso'] ?? 0) > round($credito->cuotas()->get()->sum('saldo_por_pagar'), 2)) {
                throw ValidationException::withMessages(['monto_compromiso' => 'El compromiso no puede superar la deuda pendiente.']);
            }
            $gestion = GestionCobro::create($datos + [
                'credito_id' => $credito->id, 'vendedor_id' => $credito->vendedor_id,
                'usuario_id' => $actor->id, 'usuario_nombre' => $actor->name, 'created_at' => now(),
                'pago_base_id' => $credito->pagos()->max('id') ?? 0,
            ]);
            $this->auditar($credito->vendedor, 'gestion_cobro', 'gestiones_cobro', $gestion->id, null, $datos, 'Registro de gestión de cobro', $actor);

            return $gestion;
        });
    }

    public function motivo(string $motivo): string
    {
        $motivo = trim($motivo);
        Validator::make(['motivo' => $motivo], ['motivo' => 'required|string|min:10|max:2000'])->validate();

        return $motivo;
    }

    public function regularizarVenta(Venta $venta, Vendedor $vendedor, string $motivo, User $actor): void
    {
        $motivo = $this->motivo($motivo);
        DB::transaction(function () use ($venta, $vendedor, $motivo, $actor) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->vendedor_id !== null) {
                throw ValidationException::withMessages(['venta' => 'La venta ya tiene vendedor de origen; no se sustituye su atribución.']);
            }
            $venta->update(['vendedor_id' => $vendedor->id]);
            $credito = $venta->credito()->lockForUpdate()->first();
            $origenAnterior = $credito?->vendedorOrigen;
            $antes = ['vendedor_id' => null, 'credito_origen' => $credito?->vendedor_origen_id, 'origen_reconstruido' => $credito?->vendedor_origen_reconstruido];
            if ($credito && (! $credito->vendedor_origen_id || $credito->vendedor_origen_reconstruido)) {
                $credito->update(['vendedor_origen_id' => $vendedor->id, 'vendedor_origen_reconstruido' => false]);
            }
            foreach (collect([$origenAnterior, $vendedor])->filter()->unique('id') as $afectado) {
                $this->auditar($afectado, 'regularizacion_venta', 'ventas', $venta->id, $antes,
                    ['vendedor_id' => $vendedor->id, 'credito_origen' => $credito?->vendedor_origen_id, 'origen_reconstruido' => $credito?->vendedor_origen_reconstruido], $motivo, $actor);
            }
        });
    }

    public function asignarCliente(Cliente $cliente, ?int $destinoId, string $motivo, User $actor, ?int $carteraId = null): void
    {
        $motivo = $this->motivo($motivo);
        DB::transaction(function () use ($cliente, $destinoId, $motivo, $actor, $carteraId) {
            $cliente = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            $antes = $cliente->only(['vendedor_id', 'cartera_id']);
            $anterior = $cliente->vendedor;
            $destino = $destinoId ? Vendedor::whereKey($destinoId)->where('activo', true)->sharedLock()->firstOrFail() : null;
            if ($carteraId && ! Cartera::whereKey($carteraId)->where('activa', true)->where(fn ($q) => $q->whereNull('vendedor_id')->orWhere('vendedor_id', $destinoId))->exists()) {
                throw ValidationException::withMessages(['cartera_id' => 'La cartera debe corresponder al nuevo asesor.']);
            }
            $cliente->update(['vendedor_id' => $destino?->id, 'cartera_id' => $carteraId]);
            foreach (collect([$anterior, $destino])->filter()->unique('id') as $afectado) {
                $this->auditar($afectado, 'asignacion_cliente', 'clientes', $cliente->id, $antes, $cliente->only(['vendedor_id', 'cartera_id']), $motivo, $actor);
            }
        });
    }

    public function regularizarPago(Pago $pago, Vendedor $vendedor, string $motivo, User $actor): void
    {
        $motivo = $this->motivo($motivo);
        DB::transaction(function () use ($pago, $vendedor, $motivo, $actor) {
            $pago = Pago::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            if ($pago->vendedor_id !== null && ! $pago->vendedor_reconstruido) {
                throw ValidationException::withMessages(['pago' => 'Este recibo conserva la atribución registrada al cobrar y no admite regularización histórica.']);
            }
            $anterior = $pago->vendedor;
            $antes = $pago->only(['vendedor_id', 'vendedor_reconstruido', 'vendedor_revisado_at']);
            $pago->update(['vendedor_id' => $vendedor->id, 'vendedor_reconstruido' => true, 'vendedor_revisado_at' => now()]);
            foreach (collect([$anterior, $vendedor])->filter()->unique('id') as $afectado) {
                $this->auditar($afectado, 'regularizacion_recibo', 'pagos', $pago->id, $antes, $pago->only(['vendedor_id', 'vendedor_reconstruido', 'vendedor_revisado_at']), $motivo, $actor);
            }
        });
    }
}
