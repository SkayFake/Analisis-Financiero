<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CobranzaNotificacion;
use App\Models\CobranzaSecuencia;
use App\Models\CondicionCredito;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Services\CobranzaNotificacionService;
use App\Services\CobranzaSecuenciaService;
use App\Services\EvaluacionCincoCService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CobranzaSeguimientoYSecuenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $cliente;
    private CondicionCredito $condicion;
    private Vendedor $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos si no existen
        foreach (['cobros.view', 'cobros.manage', 'clientes.view', 'creditos.view', 'creditos.create', 'facturacion.emitir', 'facturacion.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cobros.view', 'cobros.manage', 'clientes.view', 'creditos.view']);

        $this->vendedor = Vendedor::create([
            'codigo' => 'VEN-TEST',
            'nombre' => 'Vendedor Prueba',
            'meta_mensual' => 1000,
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'codigo' => 'CLI-0001',
            'nombre' => 'Juan Pérez Comercial',
            'tipo' => 'natural',
            'dui' => '02456789-1',
            'telefono' => '2222-3333',
            'celular' => '7777-8888',
            'email' => 'juan.perez@ejemplo.com',
            'ingresos' => 1200.00,
            'egresos' => 400.00,
            'activo' => true,
            'vendedor_id' => $this->vendedor->id,
            'clasificacion_cobro' => 'A',
        ]);

        $this->condicion = CondicionCredito::create([
            'nombre' => '30 días comercial con pronto pago',
            'descripcion' => 'Término 2/10, neto 30',
            'activa' => true,
            'tasa_interes_anual' => 0.00,
            'tasa_mora_mensual' => 3.00,
            'dias_gracia' => 3,
            'dias_pronto_pago' => 10,
            'descuento_pronto_pago' => 2.00,
            'termino_pago' => '2/10, neto 30',
            'frecuencia_pago' => 'mensual',
            'plazo_maximo_cuotas' => 1,
            'monto_auto_aprobado' => 500.00,
            'requiere_fiador' => false,
            'requiere_dui' => false,
        ]);
    }

    private function crearCredito(float $monto = 1000.00, int $diasMora = 0): Credito
    {
        $credito = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'condicion_credito_id' => $this->condicion->id,
            'monto_original' => $monto,
            'saldo_actual' => $monto,
            'interes_acumulado' => 0,
            'mora_acumulada' => 0,
            'tasa_interes_anual' => 0,
            'tasa_mora_mensual' => 3.00,
            'dias_gracia' => 3,
            'dias_pronto_pago' => 10,
            'descuento_pronto_pago' => 2.00,
            'termino_pago' => '2/10, neto 30',
            'fecha_limite_pronto_pago' => today()->addDays(10),
            'numero_cuotas' => 1,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 30,
            'fecha_solicitud' => today()->subDays(15),
            'fecha_aprobacion' => today()->subDays(15),
            'fecha_primera_cuota' => today()->addDays(15),
            'fecha_vencimiento' => today()->addDays(15),
            'estado' => $diasMora > 0 ? 'vencido' : 'vigente',
            'dias_mora' => $diasMora,
        ]);

        return $credito;
    }

    public function test_secuencia_de_cobranza_etapas_y_escalamiento(): void
    {
        $credito = $this->crearCredito(monto: 1000, diasMora: 20);
        $service = app(CobranzaSecuenciaService::class);

        // Inicialización
        $secuencia = $service->obtenerOSincronizar($credito);
        $this->assertInstanceOf(CobranzaSecuencia::class, $secuencia);
        $this->assertSame($credito->id, $secuencia->credito_id);

        // Escalar de etapa vía HTTP
        $response = $this->actingAs($this->admin)->post(route('cobros.secuencia.escalar', $credito), [
            'etapa_destino' => 'llamada',
            'motivo' => 'Cliente no respondió notificación escrita inicial tras 15 días.',
        ]);

        $response->assertRedirect();
        $this->assertSame('llamada', $credito->fresh()->secuenciaCobranza->etapa_actual);

        // Escalar a visita
        $service->escalar($credito, 'visita', 'Sin contacto telefónico tras 3 intentos', $this->admin);
        $this->assertSame('visita', $credito->fresh()->secuenciaCobranza->etapa_actual);

        // Escalar a agencia de cobranza
        $service->escalar($credito, 'agencia_cobranza', 'Mora prolongada mayor a 60 días', $this->admin, 'Recuperadora Central S.A.');
        $this->assertSame('agencia_cobranza', $credito->fresh()->secuenciaCobranza->etapa_actual);
        $this->assertSame('Recuperadora Central S.A.', $credito->fresh()->secuenciaCobranza->agencia_nombre);

        // Escalar a recurso legal
        $service->escalar($credito, 'recurso_legal', 'Juicio ejecutivo mercantil', $this->admin, null, 'Juzgado 1° Mercantil Exp. 45-2026');
        $this->assertSame('recurso_legal', $credito->fresh()->secuenciaCobranza->etapa_actual);
        $this->assertSame('Juzgado 1° Mercantil Exp. 45-2026', $credito->fresh()->secuenciaCobranza->expediente_legal_referencia);
    }

    public function test_notificacion_cobros_pendientes_via_correo(): void
    {
        Mail::fake();
        $credito = $this->crearCredito(monto: 850, diasMora: 5);

        $response = $this->actingAs($this->admin)->post(route('cobros.notificaciones.enviar'), [
            'cliente_id' => $this->cliente->id,
            'credito_id' => $credito->id,
            'tipo_notificacion' => 'cobro_vencido',
            'canal' => 'correo',
            'etapa_secuencia' => 'notificacion_escrita',
            'destinatario_contacto' => 'juan.perez@ejemplo.com',
            'asunto' => 'Aviso de Cobro en Mora — Crédito ' . $credito->numero,
            'mensaje' => 'Estimado cliente, su cuota comercial presenta 5 días de retraso. Le solicitamos regularizar su pago.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cobranza_notificaciones', [
            'cliente_id' => $this->cliente->id,
            'canal' => 'correo',
            'tipo_notificacion' => 'cobro_vencido',
            'estado_respuesta' => 'pendiente_respuesta',
        ]);
    }

    public function test_notificacion_cobro_realizado_y_control_respuesta(): void
    {
        $credito = $this->crearCredito(monto: 500);

        // Registrar llamada
        $notifService = app(CobranzaNotificacionService::class);
        $notificacion = $notifService->registrarNotificacion([
            'cliente_id' => $this->cliente->id,
            'credito_id' => $credito->id,
            'tipo_notificacion' => 'cobro_pendiente',
            'canal' => 'llamada',
            'destinatario_contacto' => '7777-8888',
            'asunto' => 'Llamada de recordatorio de pago preventivo',
            'mensaje' => 'Se llamó al cliente al número celular para confirmar fecha de abono.',
            'estado_respuesta' => 'pendiente_respuesta',
        ], $this->admin);

        $this->assertSame('llamada', $notificacion->canal);
        $this->assertSame('pendiente_respuesta', $notificacion->estado_respuesta);

        // Actualizar respuesta del cliente: cliente contestó con promesa de pago
        $response = $this->actingAs($this->admin)->post(route('cobros.notificaciones.respuesta', $notificacion), [
            'estado_respuesta' => 'promesa_pago',
            'fecha_compromiso' => today()->addDays(3)->toDateString(),
            'monto_compromiso' => 250.00,
            'notas_respuesta' => 'El cliente contestó muy amable y prometió realizar abono de $250 el día viernes.',
        ]);

        $response->assertRedirect();
        $notificacion->refresh();

        $this->assertSame('promesa_pago', $notificacion->estado_respuesta);
        $this->assertSame('250.00', (string) $notificacion->monto_compromiso);
        $this->assertNotNull($notificacion->fecha_respuesta);
    }

    public function test_descuento_pronto_pago_ejemplo_2_10_neto_30(): void
    {
        $credito = $this->crearCredito(monto: 1000.00);

        // Dentro del plazo de 10 días aplica pronto pago (2% de $1000 = $20 ahorro, paga $980)
        $this->assertTrue($credito->aplicaProntoPago(today()));
        $this->assertEquals(20.00, $credito->montoAhorroProntoPago());
        $this->assertEquals(980.00, $credito->montoConProntoPago());

        // Después del plazo (día 15), no aplica pronto pago y vence el total neto de $1000
        $this->assertFalse($credito->aplicaProntoPago(today()->addDays(15)));
    }

    public function test_evaluacion_de_las_cinco_c_del_credito(): void
    {
        $credito = $this->crearCredito(monto: 600);
        $service = app(EvaluacionCincoCService::class);

        $evaluacion = $service->evaluar($this->cliente, $credito);

        $this->assertArrayHasKey('cinco_c', $evaluacion);
        $this->assertArrayHasKey('caracter', $evaluacion['cinco_c']);
        $this->assertArrayHasKey('capacidad', $evaluacion['cinco_c']);
        $this->assertArrayHasKey('capital', $evaluacion['cinco_c']);
        $this->assertArrayHasKey('colateral', $evaluacion['cinco_c']);
        $this->assertArrayHasKey('condiciones', $evaluacion['cinco_c']);

        $this->assertArrayHasKey('criterios_ampliados', $evaluacion);
        $this->assertArrayHasKey('cobertura', $evaluacion['criterios_ampliados']);
        $this->assertArrayHasKey('consistencia', $evaluacion['criterios_ampliados']);
        $this->assertArrayHasKey('categoria', $evaluacion['criterios_ampliados']);

        $this->assertGreaterThan(0, $evaluacion['score_total']);
    }

    public function test_auxiliar_contable_por_cliente(): void
    {
        $credito = $this->crearCredito(monto: 500);

        $auxiliar = $this->cliente->obtenerAuxiliar();

        $this->assertNotEmpty($auxiliar);
        $primerMov = $auxiliar->first();
        $this->assertSame('VENTA_CREDITO', $primerMov['tipo']);
        $this->assertEquals(500.00, $primerMov['cargo']);
        $this->assertEquals(500.00, $primerMov['saldo_acumulado']);

        // Vista de auxiliar accesible vía HTTP
        $response = $this->actingAs($this->admin)->get(route('clientes.auxiliar', $this->cliente));
        $response->assertOk();
        $response->assertSee('Auxiliar Contable por Cliente');
        $response->assertSee($this->cliente->nombre);
    }

    public function test_vistas_de_secuencia_y_notificaciones_responden_ok(): void
    {
        $this->crearCredito(monto: 400, diasMora: 10);

        $responseSecuencia = $this->actingAs($this->admin)->get(route('cobros.secuencia'));
        $responseSecuencia->assertOk();
        $responseSecuencia->assertSee('Plan de Secuencia de Cobranza');
        $responseSecuencia->assertSee('Notificación Escrita');
        $responseSecuencia->assertSee('Llamada Telefónica');
        $responseSecuencia->assertSee('Visita Presencial');
        $responseSecuencia->assertSee('Agencia de Cobranza');
        $responseSecuencia->assertSee('Recurso Legal');

        $responseNotif = $this->actingAs($this->admin)->get(route('cobros.notificaciones'));
        $responseNotif->assertOk();
        $responseNotif->assertSee('Control de Clientes Destinatarios');
        $responseNotif->assertSee('Historial de Notificaciones y Control de Respuesta');
    }
}
