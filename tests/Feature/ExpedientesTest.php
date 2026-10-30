<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\Credito;
use App\Models\ExpedienteDocumento;
use App\Models\ExpedienteEvento;
use App\Models\Fiador;
use App\Models\User;
use App\Models\Venta;
use App\Services\CreditoService;
use App\Services\ExpedienteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedientesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cliente $cliente;

    private ExpedienteService $servicio;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        config(['expedientes.exigir_creditos_nuevos' => true, 'erp.dte.enabled' => false]);
        Storage::fake('expedientes');
        Http::preventStrayRequests();
        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = $this->usuario(['expedientes.view', 'expedientes.upload', 'expedientes.review', 'expedientes.manage', 'creditos.view', 'creditos.approve']);
        $this->cliente = Cliente::create(['codigo' => 'DOC-CLI', 'nombre' => 'Cliente sintético', 'tipo' => 'natural', 'dui' => '01234567-8', 'nit' => '01234567-8', 'activo' => true]);
        $this->servicio = app(ExpedienteService::class);
        $this->actingAs($this->admin);
    }

    private function usuario(array $permisos): User
    {
        $u = User::factory()->create();
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $u->givePermissionTo($p);
        }

        return $u;
    }

    private function credito(bool $fiador = false, ?Cliente $cliente = null, array $extras = []): Credito
    {
        $cliente ??= $this->cliente;
        $condicion = CondicionCredito::create(['nombre' => 'Condición expediente', 'activa' => true, 'requiere_dui' => true, 'requiere_fiador' => $fiador,
            'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 12, 'compras_minimas_cliente_frecuente' => 0, 'monto_auto_aprobado' => 10000, 'tasa_interes_anual' => 0]);
        $venta = Venta::create(['cliente_id' => $cliente->id, 'codigo_generacion' => Str::uuid(), 'numero_control' => 'DOC-'.Str::random(12), 'tipo_documento' => '11',
            'estado_dte' => 'local', 'fecha_emision' => today(), 'hora_emision' => '12:00:00', 'condicion_operacion' => '2', 'total_pagar' => 300]);

        return app(CreditoService::class)->crearCreditoDesdeVenta($venta, array_merge(['condicion_credito_id' => $condicion->id, 'numero_cuotas' => 3,
            'fecha_primera_cuota' => today()->addMonth()->toDateString(), 'dui_verificado' => true], $extras));
    }

    private function documento(string $categoria = 'dui_completo', ?Credito $credito = null, ?Fiador $fiador = null, array $datos = []): ExpedienteDocumento
    {
        return $this->servicio->subir($credito?->cliente ?? $this->cliente, $credito, $fiador,
            UploadedFile::fake()->createWithContent('documento.pdf', self::PDF), array_merge(['categoria' => $categoria], $datos), $this->admin);
    }

    private function validar(ExpedienteDocumento $d): void
    {
        $this->servicio->revisar($d, 'validado', 'Corresponde al titular y es legible en esta prueba.', $this->admin);
    }

    private function completar(Credito $credito, ?Fiador $fiador = null): void
    {
        foreach ($fiador ? ['dui_completo', 'domicilio', 'constancia_salario', 'fianza'] : ['dui_completo', 'domicilio', 'estado_cuenta', 'solicitud', 'contrato'] as $categoria) {
            $contexto = $fiador || in_array($categoria, ['solicitud', 'contrato']) ? $credito : null;
            $this->validar($this->documento($categoria, $contexto, $fiador));
        }
    }

    private function datosFiador(array $extras = []): array
    {
        return array_merge(['nombre' => 'Fiador sintético', 'dui' => '12345678-4', 'telefono' => '7123-4567', 'direccion' => 'Dirección de prueba',
            'ingresos' => '900.00', 'egresos' => '350.00', 'motivo' => 'Presentación de fiador para la solicitud.'], $extras);
    }

    public function test_credito_nuevo_no_se_aprueba_por_marcar_dui_y_guarda_politica(): void
    {
        $credito = $this->credito(extras: ['expediente_requerido' => false]);
        $this->assertTrue($credito->expediente_requerido);
        $this->assertFalse($credito->dui_verificado);
        $this->assertSame('pendiente_aprobacion', $credito->estado);
        $this->assertNotEmpty($credito->requisitos_expediente['cliente']['identidad']);
        $this->assertDatabaseCount('cuotas', 0);
        $this->post(route('creditos.aprobar', $credito), ['motivo' => 'Revisado para aprobación de prueba.'])->assertSessionHasErrors('aprobacion_error');
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_completar_revisar_y_aprobar_guarda_evidencia_historica_y_permite_pago(): void
    {
        $credito = $this->credito();
        $this->completar($credito);
        $this->assertSame([], $this->servicio->faltantes($credito));
        $this->post(route('creditos.aprobar', $credito), ['motivo' => 'Capacidad y comprobantes revisados satisfactoriamente.'])->assertSessionHasNoErrors();
        $this->assertSame('vigente', $credito->fresh()->estado);
        $this->assertTrue($credito->fresh()->dui_verificado);
        $this->assertDatabaseCount('cuotas', 3);
        $evento = ExpedienteEvento::where('accion', 'aprobacion_credito')->sole();
        $this->assertCount(5, $evento->despues['comprobacion']);
        $this->assertSame($this->admin->id, $evento->usuario_id);
        $historico = $evento->despues;
        $d = ExpedienteDocumento::first();
        $this->servicio->revisar($d, 'anulado', 'Posterior anulación de prueba con evidencia conservada.', $this->admin);
        app(CreditoService::class)->procesarPago($credito, ['monto' => 100]);
        $this->assertSame('200.00', $credito->fresh()->saldo_actual);
        $this->assertSame($historico, $evento->fresh()->despues);
    }

    public function test_dui_frente_y_reverso_cuentan_como_identidad_solo_si_ambos_estan_validados(): void
    {
        $credito = $this->credito();
        $this->validar($this->documento('dui_frente'));
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
        $reverso = $this->documento('dui_reverso');
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
        $this->validar($reverso);
        $this->assertTrue($this->servicio->lista($credito)[0]['completo']);
    }

    public function test_carga_pdf_privada_sin_estado_de_validacion_controlado_por_usuario(): void
    {
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'dui_completo', 'archivo' => UploadedFile::fake()->createWithContent('prueba.pdf', self::PDF),
            'estado' => 'validado', 'subido_por' => 999, 'ruta' => '../../public/robo.pdf'])->assertSessionHasNoErrors();
        $d = ExpedienteDocumento::sole();
        $this->assertSame('pendiente', $d->estado);
        $this->assertSame($this->admin->id, $d->subido_por);
        $this->assertStringStartsWith($this->cliente->id.'/', $d->ruta);
        Storage::disk('expedientes')->assertExists($d->ruta);
        $this->assertFalse(config('filesystems.disks.expedientes.serve'));
        $this->get(route('expedientes.descargar', [$this->cliente, $d]))->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('expediente_eventos', ['accion' => 'descarga', 'documento_id' => $d->id]);
        $this->get('/storage/'.$d->ruta)->assertForbidden();
    }

    public function test_acepta_imagen_png_detectada_por_contenido(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7G8AAAAASUVORK5CYII=');
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'dui_frente', 'archivo' => UploadedFile::fake()->createWithContent('dui.png', $png)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expediente_documentos', ['mime' => 'image/png', 'bytes' => strlen($png)]);
    }

    public static function archivosInvalidos(): array
    {
        return [['mal.pdf', '<script>alert(1)</script>'], ['imagen.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            ['copia.jpg', self::PDF], ['vacio.pdf', ''], ['incompleto.pdf', '%PDF-1.4 sin final'], ['archivo.php', '<?php phpinfo();'], ['grande.pdf', self::PDF, true]];
    }

    #[DataProvider('archivosInvalidos')]
    public function test_rechaza_archivos_incoherentes_y_no_deja_rastros(string $nombre, string $contenido, bool $grande = false): void
    {
        if ($grande) {
            $contenido .= str_repeat('a', 2048 * 1024);
        }
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'dui_completo', 'archivo' => UploadedFile::fake()->createWithContent($nombre, $contenido)])->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('expediente_documentos', 0);
        $this->assertSame([], Storage::disk('expedientes')->allFiles());
    }

    public function test_permiso_de_creditos_no_da_acceso_a_documentos_ni_historial(): void
    {
        $d = $this->documento();
        $credito = $this->credito();
        $this->actingAs($this->usuario(['creditos.view']));
        $this->get(route('expedientes.cliente', $this->cliente))->assertForbidden();
        $this->get(route('expedientes.credito', $credito))->assertForbidden();
        $this->get(route('expedientes.descargar', [$this->cliente, $d]))->assertForbidden();
        $this->post(route('expedientes.cliente.subir', $this->cliente), [])->assertForbidden();
        $this->post(route('expedientes.revisar', [$this->cliente, $d]), ['estado' => 'validado', 'motivo' => 'Intento de prueba.'])->assertForbidden();
    }

    public function test_cargador_no_puede_revisar_y_lector_no_puede_cargar(): void
    {
        $d = $this->documento();
        $this->actingAs($this->usuario(['expedientes.view', 'expedientes.upload']));
        $this->post(route('expedientes.revisar', [$this->cliente, $d]), ['estado' => 'validado', 'motivo' => 'Intento de prueba.'])->assertForbidden();
        $this->actingAs($this->usuario(['expedientes.view']));
        $this->get(route('expedientes.cliente', $this->cliente))->assertOk()->assertDontSee('Guardar comprobante pendiente');
        $this->post(route('expedientes.cliente.subir', $this->cliente), [])->assertForbidden();
    }

    public function test_archivo_de_otro_cliente_no_puede_descargarse_ni_revisarse_con_padre_falso(): void
    {
        $otro = Cliente::create(['codigo' => 'DOC-OTRO', 'nombre' => 'Otro cliente', 'tipo' => 'natural']);
        $d = $this->documento();
        $this->get(route('expedientes.descargar', [$otro, $d]))->assertNotFound();
        $this->post(route('expedientes.revisar', [$otro, $d]), ['estado' => 'validado', 'motivo' => 'Intento de prueba.'])->assertNotFound();
    }

    public function test_visitante_se_redirige_al_login_incluso_con_id_conocido(): void
    {
        $d = $this->documento();
        $this->app['auth']->forgetGuards();
        $this->get(route('expedientes.descargar', [$this->cliente, $d]))->assertRedirect('/login');
    }

    public function test_reemplazo_conserva_original_y_exige_nueva_revision(): void
    {
        $primero = $this->documento();
        $this->validar($primero);
        $nuevo = $this->documento(datos: ['reemplaza_id' => $primero->id, 'motivo' => 'Copia más legible presentada por el cliente.']);
        $this->assertFalse($primero->fresh()->vigente);
        $this->assertSame('validado', $primero->fresh()->estado);
        $this->assertSame(2, $nuevo->version);
        $this->assertSame($primero->grupo, $nuevo->grupo);
        $this->assertSame('pendiente', $nuevo->estado);
        Storage::disk('expedientes')->assertExists($primero->ruta);
        $this->get(route('expedientes.descargar', [$this->cliente, $primero]))->assertOk();
        $this->expectException(ValidationException::class);
        $this->validar($primero->fresh());
    }

    public function test_reemplazo_de_otro_ambito_es_rechazado(): void
    {
        $credito = $this->credito();
        $d = $this->documento('otro');
        $this->post(route('expedientes.credito.subir', $credito), ['categoria' => 'otro', 'archivo' => UploadedFile::fake()->createWithContent('otro.pdf', self::PDF), 'reemplaza_id' => $d->id, 'motivo' => 'Intento de cruce documental.'])->assertNotFound();
        $this->assertTrue($d->fresh()->vigente);
        $this->assertDatabaseCount('expediente_documentos', 1);
    }

    public function test_error_de_bitacora_revierte_metadata_y_elimina_archivo_nuevo(): void
    {
        $anterior = $this->documento();
        $fallo = \Mockery::mock(ExpedienteService::class)->makePartial();
        $fallo->shouldReceive('evento')->once()->andThrow(new \RuntimeException('Bitácora fallida'));
        try {
            $fallo->subir($this->cliente, null, null, UploadedFile::fake()->createWithContent('copia.pdf', self::PDF), ['categoria' => 'dui_completo', 'reemplaza_id' => $anterior->id, 'motivo' => 'Prueba de reversión del archivo.'], $this->admin);
            $this->fail('Se esperaba un error de bitácora.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Bitácora fallida', $e->getMessage());
        }
        $this->assertDatabaseCount('expediente_documentos', 1);
        $this->assertTrue($anterior->fresh()->vigente);
        $this->assertSame([$anterior->ruta], Storage::disk('expedientes')->allFiles());
    }

    public function test_documento_vencido_rechazado_o_anulado_no_satisface_requisito(): void
    {
        $credito = $this->credito();
        $d = $this->documento(datos: ['fecha_emision' => today()->subYear()->toDateString(), 'fecha_vencimiento' => today()->subDay()->toDateString()]);
        $this->post(route('expedientes.revisar', [$this->cliente, $d]), ['estado' => 'validado', 'motivo' => 'Revisión de documento vencido.'])->assertSessionHasErrors('estado');
        $this->servicio->revisar($d, 'rechazado', 'Documento vencido no aceptado.', $this->admin);
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
        $this->servicio->revisar($d, 'anulado', 'Anulación documentada del comprobante.', $this->admin);
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
    }

    public function test_vencimiento_posterior_a_revision_y_cambio_de_identidad_exigen_renovar(): void
    {
        $credito = $this->credito();
        $d = $this->documento(datos: ['fecha_emision' => today()->toDateString(), 'fecha_vencimiento' => today()->addDay()->toDateString()]);
        $this->validar($d);
        $this->assertTrue($this->servicio->lista($credito)[0]['completo']);
        $this->travel(2)->days();
        $this->assertFalse($this->servicio->lista($credito->fresh())[0]['completo']);
        $this->travelBack();
        $this->cliente->update(['dui' => '12345678-4']);
        $this->assertFalse($this->servicio->lista($credito->fresh())[0]['completo']);
        $this->post(route('expedientes.revisar', [$this->cliente, $d]), ['estado' => 'validado', 'motivo' => 'Intento con titular cambiado.'])->assertSessionHasErrors('estado');
    }

    public function test_archivo_alterado_no_se_descarga_ni_cuenta_para_aprobar(): void
    {
        $credito = $this->credito();
        $d = $this->documento();
        $this->validar($d);
        Storage::disk('expedientes')->put($d->ruta, 'modificado');
        $this->get(route('expedientes.descargar', [$this->cliente, $d]))->assertStatus(409);
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
        Storage::disk('expedientes')->delete($d->ruta);
        $this->assertFalse($this->servicio->lista($credito)[0]['completo']);
    }

    public function test_documentos_del_contrato_no_se_reutilizan_en_otro_credito(): void
    {
        $uno = $this->credito();
        $dos = $this->credito();
        $this->completar($uno);
        $this->assertSame([], $this->servicio->faltantes($uno));
        $this->assertCount(2, $this->servicio->faltantes($dos));
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'contrato', 'archivo' => UploadedFile::fake()->createWithContent('contrato.pdf', self::PDF)])->assertSessionHasErrors('categoria');
    }

    public function test_fiador_exige_datos_y_comprobantes_propios_aunque_cliente_este_completo(): void
    {
        $credito = $this->credito(true);
        $this->completar($credito);
        $this->post(route('creditos.aprobar', $credito), ['motivo' => 'Falta fiador en la solicitud.'])->assertSessionHasErrors('aprobacion_error');
        $f = $this->servicio->guardarFiador($credito, null, $this->datosFiador(), $this->admin);
        $this->assertCount(4, $this->servicio->faltantes($credito));
        $this->completar($credito, $f);
        $this->assertSame([], $this->servicio->faltantes($credito));
        $this->post(route('creditos.aprobar', $credito), ['motivo' => 'Cliente y fiador completamente revisados.'])->assertSessionHasNoErrors();
        $this->assertSame('vigente', $credito->fresh()->estado);
        $this->assertCount(1, ExpedienteEvento::where('accion', 'aprobacion_credito')->sole()->despues['fiadores']);
        $this->put(route('expedientes.fiador.actualizar', [$credito, $f]), $this->datosFiador(['nombre' => 'Nombre cambiado']))->assertSessionHasErrors('fiador');
    }

    public function test_fiador_opcional_registrado_tambien_debe_tener_expediente(): void
    {
        $credito = $this->credito();
        $this->completar($credito);
        $this->servicio->guardarFiador($credito, null, $this->datosFiador(), $this->admin);
        $this->assertCount(4, $this->servicio->faltantes($credito));
    }

    public function test_fiador_de_otro_credito_no_admite_carga_con_padre_falso(): void
    {
        $uno = $this->credito();
        $dos = $this->credito();
        $f = $this->servicio->guardarFiador($uno, null, $this->datosFiador(), $this->admin);
        $this->post(route('expedientes.fiador.subir', [$dos, $f]), ['categoria' => 'dui_completo', 'archivo' => UploadedFile::fake()->createWithContent('dui.pdf', self::PDF)])->assertNotFound();
        $this->assertDatabaseCount('expediente_documentos', 0);
    }

    public function test_fiador_no_puede_ser_cliente_o_duplicarse_y_valida_dui_telefono_nit(): void
    {
        $credito = $this->credito();
        $this->post(route('expedientes.fiador.crear', $credito), $this->datosFiador(['dui' => $this->cliente->dui]))->assertSessionHasErrors('dui');
        $this->post(route('expedientes.fiador.crear', $credito), $this->datosFiador(['dui' => 'basura', 'telefono' => '123', 'nit' => 'no']))->assertSessionHasErrors(['dui', 'telefono', 'nit']);
        $this->servicio->guardarFiador($credito, null, $this->datosFiador(), $this->admin);
        $this->post(route('expedientes.fiador.crear', $credito), $this->datosFiador())->assertSessionHasErrors('dui');
        $this->assertDatabaseCount('fiadores', 1);
        $this->assertSame($this->cliente->id, $this->cliente->fiadores()->first()->cliente->id);
    }

    public function test_persona_juridica_usa_dui_del_representante_y_requiere_personeria(): void
    {
        $this->cliente->update(['tipo' => 'juridica', 'dui' => null, 'nit' => '0614-010101-101-0']);
        $this->cliente->datosJuridicos()->create(['representante_legal' => 'Representante sintético', 'dui_representante' => '01234567-8']);
        $credito = $this->credito();
        foreach (['dui_completo', 'nit', 'constitucion', 'representacion', 'domicilio', 'estados_financieros'] as $categoria) {
            $this->validar($this->documento($categoria));
        }
        foreach (['solicitud', 'contrato'] as $categoria) {
            $this->validar($this->documento($categoria, $credito));
        }
        $this->assertSame([], $this->servicio->faltantes($credito));
        $this->assertTrue(app(CreditoService::class)->cumpleRequisitosAprobacion($credito));
        app(CreditoService::class)->aprobarCredito($credito, 'Representación y solvencia de la sociedad revisadas.', $this->admin->id);
        $this->assertSame('vigente', $credito->fresh()->estado);
    }

    public function test_listas_y_bitacora_se_renderizan_sin_exponer_rutas_privadas(): void
    {
        $credito = $this->credito();
        $d = $this->documento();
        $this->get(route('expedientes.credito', $credito))->assertOk()->assertSee('Control previo a aprobación')->assertSee('Pendiente')->assertDontSee($d->ruta);
        $this->get(route('clientes.show', $this->cliente))->assertOk()->assertSee('Expediente documental');
        $this->get(route('creditos.show', $credito))->assertOk()->assertSee('Expediente documental');
    }

    public function test_historial_no_se_modifica_por_modelo_ni_sql(): void
    {
        $this->documento();
        $evento = ExpedienteEvento::first();
        try {
            $evento->update(['motivo' => 'Otro']);
            $this->fail('Debe ser inmutable.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('solo inserción', $e->getMessage());
        }
        if (DB::getDriverName() === 'pgsql') {
            $this->expectException(QueryException::class);
            DB::transaction(fn () => DB::table('expediente_eventos')->where('id', $evento->id)->delete());
        }
    }

    public function test_politica_de_solicitud_no_cambia_si_se_modifica_configuracion_general(): void
    {
        $credito = $this->credito();
        config(['expedientes.requisitos.natural' => []]);
        $this->assertCount(5, $this->servicio->faltantes($credito));
    }

    public function test_legacy_conserva_aprobacion_y_pagos_sin_archivos(): void
    {
        config(['expedientes.exigir_creditos_nuevos' => false]);
        $credito = $this->credito();
        $this->assertFalse($credito->expediente_requerido);
        $this->assertSame('vigente', $credito->estado);
        app(CreditoService::class)->procesarPago($credito, ['monto' => 100]);
        $this->assertSame('200.00', $credito->fresh()->saldo_actual);
        $this->assertDatabaseCount('expediente_documentos', 0);
    }

    public function test_vencimiento_sin_fecha_emision_y_fechas_incoherentes(): void
    {
        $this->documento(datos: ['fecha_vencimiento' => today()->addYear()->toDateString()]);
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'domicilio', 'archivo' => UploadedFile::fake()->createWithContent('fecha.pdf', self::PDF),
            'fecha_emision' => today()->addDay()->toDateString()])->assertSessionHasErrors('fecha_emision');
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'domicilio', 'archivo' => UploadedFile::fake()->createWithContent('fecha.pdf', self::PDF),
            'fecha_emision' => today()->toDateString(), 'fecha_vencimiento' => today()->subDay()->toDateString()])->assertSessionHasErrors('fecha_vencimiento');
        $this->assertDatabaseCount('expediente_documentos', 1);
    }

    public function test_fiador_creado_desde_venta_no_puede_ser_el_propio_cliente(): void
    {
        $credito = $this->credito(true, extras: ['fiador' => ['nombre' => $this->cliente->nombre, 'dui' => $this->cliente->dui, 'telefono' => '7123-4567', 'direccion' => 'Dirección sintética']]);
        $this->completar($credito);
        $f = $credito->fiadores()->first();
        $f->update(['ingresos' => 900, 'egresos' => 350]);
        $this->completar($credito, $f);
        $this->post(route('creditos.aprobar', $credito), ['motivo' => 'Prueba de independencia de garantía.'])->assertSessionHasErrors('aprobacion_error');
        $this->assertSame('pendiente_aprobacion', $credito->fresh()->estado);
    }

    public function test_cada_fiador_adicional_requiere_sus_propios_documentos(): void
    {
        $credito = $this->credito(true);
        $this->completar($credito);
        $uno = $this->servicio->guardarFiador($credito, null, $this->datosFiador(), $this->admin);
        $this->completar($credito, $uno);
        $dos = $this->servicio->guardarFiador($credito, null, $this->datosFiador(['nombre' => 'Segundo fiador', 'dui' => '79000001-3']), $this->admin);
        $this->assertCount(4, $this->servicio->faltantes($credito));
        $this->completar($credito, $dos);
        $this->assertSame([], $this->servicio->faltantes($credito));
    }

    public function test_cambio_de_tipo_de_cliente_no_permite_usar_politica_anterior(): void
    {
        $credito = $this->credito();
        $this->cliente->update(['tipo' => 'juridica']);
        $this->assertStringContainsString('tipo de cliente cambió', $this->servicio->faltantes($credito->fresh())[0]);
    }

    public function test_limite_de_documentos_y_fallo_de_disco_no_dejan_metadata(): void
    {
        config(['expedientes.max_documentos_por_cliente' => 1]);
        $this->documento();
        $this->post(route('expedientes.cliente.subir', $this->cliente), ['categoria' => 'domicilio', 'archivo' => UploadedFile::fake()->createWithContent('otro.pdf', self::PDF)])->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('expediente_documentos', 1);
        config(['expedientes.max_documentos_por_cliente' => 10]);
        $disk = Storage::disk('expedientes');
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('expedientes')->andReturn($mock);
        try {
            $this->documento('domicilio');
            $this->fail('Se esperaba fallo de almacenamiento.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('guardar el comprobante', $e->getMessage());
        }
        $this->assertDatabaseCount('expediente_documentos', 1);
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_cambiar_ingresos_del_fiador_exige_presentar_su_respaldo_actualizado(): void
    {
        $credito = $this->credito(true);
        $this->completar($credito);
        $f = $this->servicio->guardarFiador($credito, null, $this->datosFiador(), $this->admin);
        $this->completar($credito, $f);
        $this->assertSame([], $this->servicio->faltantes($credito));
        $this->servicio->guardarFiador($credito, $f, $this->datosFiador(['ingresos' => '2000.00']), $this->admin);
        $faltantes = $this->servicio->faltantes($credito->fresh());
        $this->assertCount(1, $faltantes);
        $this->assertStringContainsString('ingresos', $faltantes[0]);
    }

    public function test_actualizacion_activa_control_en_pendientes_anteriores_sin_reabrir_creditos_otorgados(): void
    {
        config(['expedientes.exigir_creditos_nuevos' => false]);
        $otorgado = $this->credito();
        $pendiente = $this->credito();
        $pendiente->update(['estado' => 'pendiente_aprobacion', 'fecha_aprobacion' => null]);
        $cuotas = $otorgado->cuotas()->get()->toArray();
        $migracion = require database_path('migrations/2026_10_30_020000_create_expedientes_documentales.php');
        $migracion->actualizarSolicitudesPendientes();
        $migracion->actualizarSolicitudesPendientes();
        $this->assertTrue($pendiente->fresh()->expediente_requerido);
        $this->assertFalse($pendiente->fresh()->dui_verificado);
        $this->assertSame('pendiente_aprobacion', $pendiente->fresh()->estado);
        $this->assertFalse($otorgado->fresh()->expediente_requerido);
        $this->assertSame('vigente', $otorgado->fresh()->estado);
        $this->assertSame($cuotas, $otorgado->cuotas()->get()->toArray());
        $this->assertSame(1, ExpedienteEvento::where('accion', 'control_activado')->count());
        $this->assertCount(5, $this->servicio->faltantes($pendiente->fresh()));
    }
}
