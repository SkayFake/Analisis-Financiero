<?php

namespace Tests\Feature;

use App\Livewire\ActivoFijoDashboard;
use App\Livewire\Configuracion\CatalogosDashboard;
use App\Livewire\InventarioDashboard;
use App\Livewire\MovimientoInventarioModal;
use App\Livewire\PuntoVenta;
use App\Livewire\Vendedores\VendedoresDashboard;
use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Models\Institucion;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ValidacionFormulariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Escenarios del flujo anterior; el expediente obligatorio se cubre en ExpedientesTest.
        config(['expedientes.exigir_creditos_nuevos' => false]);
        $this->actingAs(User::factory()->create());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[DataProvider('clientesInvalidos')]
    public function test_cliente_rechaza_datos_incoherentes(string $campo, mixed $valor): void
    {
        $this->post('/clientes', ['tipo' => 'natural', 'nombre' => 'José Pérez', $campo => $valor])
            ->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('clientes', 0);
    }

    public static function clientesInvalidos(): array
    {
        return [
            ['telefono', 'hola mundo'], ['telefono', '12345678'], ['celular', '2234-5678'],
            ['dui', '01234567-9'], ['dui', '00000000-0'], ['nit', '0614-010101-001-4'],
            ['nrc', 'ABC123'], ['email', 'sin-arroba'], ['nombre', '123456'], ['nombre', '<script>hola</script>'],
            ['ingresos', -1], ['egresos', '1.234'], ['ingresos', '1000000000000'],
            ['departamento', 'Guatemala'], ['zona_id', 999], ['vendedor_id', 'abc'],
            ['nombre_comercial', 'No corresponde a persona natural'], ['telefono', ['71234567']],
        ];
    }

    public function test_cliente_normaliza_contacto_y_dui_homologado(): void
    {
        $this->post('/clientes', [
            'tipo' => 'natural', 'nombre' => "José O'Connor", 'dui' => '012345678', 'nit' => '01234567-8',
            'telefono' => '+503 2234-5678', 'celular' => '+50371234567', 'departamento' => 'la union',
            'ingresos' => '', 'egresos' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('clientes', [
            'nombre' => "José O'Connor", 'dui' => '01234567-8', 'nit' => '01234567-8',
            'telefono' => '2234-5678', 'celular' => '7123-4567', 'departamento' => 'La Unión', 'ingresos' => 0,
        ]);
    }

    public function test_homologacion_y_duplicados_se_comprueban_incluso_con_formato_antiguo(): void
    {
        $cliente = Cliente::create(['codigo' => 'C-1', 'nombre' => 'Cliente previo', 'tipo' => 'natural', 'dui' => '012345678']);
        $this->post('/clientes', ['tipo' => 'natural', 'nombre' => 'Duplicado', 'dui' => '01234567-8'])->assertSessionHasErrors('dui');
        $this->post('/clientes', ['tipo' => 'natural', 'nombre' => 'Duplicado', 'nit' => '01234567-8'])->assertSessionHasErrors('nit');
        $this->post('/clientes', ['tipo' => 'natural', 'nombre' => 'No coincide', 'dui' => '12345678-4', 'nit' => '01234567-8'])->assertSessionHasErrors('nit');
        $this->put('/clientes/'.$cliente->id, ['nombre' => 'Mismo cliente', 'dui' => '01234567-8', 'nit' => '01234567-8'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_juridica_no_acepta_dui_como_nit_ni_datos_sin_validar(): void
    {
        $this->post('/clientes', [
            'tipo' => 'juridica', 'nombre' => 'Empresa S.A. de C.V.', 'nit' => '01234567-8',
            'dui_representante' => '12345678-9', 'numero_empleados' => -2,
            'fecha_constitucion' => now()->addDay()->toDateString(), 'balance_general' => ['campo_inventado' => 100],
        ])->assertSessionHasErrors(['nit', 'dui_representante', 'numero_empleados', 'fecha_constitucion', 'balance_general']);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('clientes_juridicos', 0);
    }

    public function test_juridica_guarda_y_actualiza_datos_validados_atomicamente(): void
    {
        $datos = [
            'tipo' => 'juridica', 'nombre' => 'Empresa S.A. de C.V.', 'nit' => '06140101011010', 'nrc' => '123456-7',
            'representante_legal' => 'María Pérez', 'dui_representante' => '012345678', 'numero_empleados' => 12,
            'fecha_constitucion' => '2020-01-01', 'fecha_balance' => '2025-12-31',
            'balance_general' => ['patrimonio' => ['utilidades_retenidas' => -100.25]],
        ];
        $this->post('/clientes', $datos)->assertSessionHasNoErrors()->assertRedirect();
        $cliente = Cliente::first();
        $this->assertSame('0614-010101-101-0', $cliente->nit);
        $this->assertSame('1234567', $cliente->nrc);
        $this->assertSame('01234567-8', $cliente->datosJuridicos->dui_representante);
        $this->put('/clientes/'.$cliente->id, ['nombre' => $cliente->nombre, 'dui_representante' => '12345678-9', 'numero_empleados' => 99])
            ->assertSessionHasErrors('dui_representante');
        $this->assertSame(12, ClienteJuridico::first()->numero_empleados);
        $this->put('/clientes/'.$cliente->id, ['nombre' => $cliente->nombre, 'dui_representante' => '12345678-4', 'numero_empleados' => 15])
            ->assertSessionHasNoErrors();
        $this->assertSame(15, ClienteJuridico::first()->numero_empleados);
    }

    public function test_edicion_no_permite_cambiar_tipo_y_balance_no_antecede_constitucion(): void
    {
        $cliente = Cliente::create(['codigo' => 'J-1', 'tipo' => 'juridica', 'nombre' => 'Empresa']);
        $this->put('/clientes/'.$cliente->id, ['nombre' => 'Empresa', 'tipo' => 'natural'])->assertSessionHasErrors('tipo');
        $this->put('/clientes/'.$cliente->id, ['nombre' => 'Empresa', 'fecha_constitucion' => '2024-01-01', 'fecha_balance' => '2023-01-01'])->assertSessionHasErrors('fecha_balance');
    }

    public function test_proveedor_aplica_las_mismas_reglas_y_muestra_errores(): void
    {
        $form = Livewire::test(CatalogosDashboard::class)->call('setTab', 'proveedores')->call('create')
            ->set('nombre', 'Proveedor local')->set('nit', 'basura')->set('telefono', '12345')->set('dias_credito', -1)
            ->call('save')->assertHasErrors(['nit', 'telefono', 'dias_credito'])->assertSee('Revise los datos ingresados');
        $this->assertDatabaseCount('proveedores', 0);
        $form->set('nit', '06140101011010')->set('telefono', '+503 2234-5678')->set('dias_credito', 30)
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('proveedores', ['nit' => '0614-010101-101-0', 'telefono' => '2234-5678', 'dias_credito' => 30]);
    }

    public function test_vendedor_telefono_codigo_y_meta_se_validan_al_crear_y_editar(): void
    {
        auth()->user()->givePermissionTo(['vendedores.view', 'vendedores.manage', 'vendedores.metas']);
        $form = Livewire::test(VendedoresDashboard::class)->call('create')->set('codigo', 'VEN-1')->set('nombre', 'Vendedor')
            ->set('telefono', 'abcd')->set('meta_mensual', '1.234')->call('save')->assertHasErrors(['telefono', 'meta_mensual']);
        $form->set('telefono', '71234567')->set('meta_mensual', 100)->call('save')->assertHasNoErrors();
        $vendedor = Vendedor::first();
        $form->call('edit', $vendedor->id)->set('telefono', '+50361234567')->set('motivo', 'Actualización del teléfono de contacto')->call('save')->assertHasNoErrors();
        $this->assertSame('6123-4567', $vendedor->fresh()->telefono);
        $form->call('create')->set('codigo', 'VEN-1')->set('nombre', 'Duplicado')->call('save')->assertHasErrors('codigo');
        $this->assertDatabaseCount('vendedores', 1);
    }

    public function test_codigo_unidad_es_unico_dentro_de_su_institucion(): void
    {
        $institucion = Institucion::create(['codigo' => 'INST', 'nombre' => 'Institución']);
        Unidad::create(['institucion_id' => $institucion->id, 'codigo' => 'UNI1', 'nombre' => 'Unidad']);
        Livewire::test(CatalogosDashboard::class)->call('setTab', 'unidades_organizativas')->call('create')
            ->set('nombre', 'Otra unidad')->set('codigo', 'UNI1')->set('institucion_id', $institucion->id)
            ->call('save')->assertHasErrors('codigo');
        $this->assertDatabaseCount('unidades', 1);
    }

    public function test_fiador_no_pasa_identificadores_invalidos_en_controlador_ni_pos(): void
    {
        foreach (['creditos.create', 'facturacion.emitir'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            auth()->user()->givePermissionTo($permiso);
        }
        $this->post('/creditos', ['fiador_nombre' => 'Fiador', 'fiador_dui' => '12345678-9', 'fiador_telefono' => '12345678'])
            ->assertSessionHasErrors(['fiador_dui', 'fiador_telefono']);
        Livewire::test(PuntoVenta::class)->set('fiador_nombre', 'Fiador')->set('fiador_dui', '12345678-9')->set('fiador_telefono', '12345678')
            ->call('facturar')->assertHasErrors(['fiador_dui', 'fiador_telefono']);
    }

    public function test_movimiento_valida_selectores_tipo_cantidad_costo_y_fecha(): void
    {
        Livewire::test(MovimientoInventarioModal::class)->set('isOpen', true)->set('bodega_id', 999)->set('producto_id', 999)
            ->set('tipo', 'inventado')->set('cantidad', -1)->set('fecha_vencimiento', 'mañana')->call('guardar')
            ->assertHasErrors(['tipo', 'bodega_id', 'producto_id', 'cantidad', 'fecha_vencimiento']);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_activo_valida_fecha_importes_y_unidad_antes_de_persistir(): void
    {
        $institucion = Institucion::create(['codigo' => 'INST', 'nombre' => 'Institución']);
        $unidad = Unidad::create(['institucion_id' => $institucion->id, 'codigo' => 'UNID', 'nombre' => 'Unidad']);
        $categoria = CategoriaActivo::create(['codigo' => 'EQUI', 'nombre' => 'Equipo', 'porcentaje_depreciacion' => 20]);
        $form = Livewire::test(ActivoFijoDashboard::class)->call('abrirModalRegistro')
            ->set('form_nombre', 'Computadora')->set('form_unidad_id', $unidad->id)->set('form_categoria_id', $categoria->id)
            ->set('form_valor_adquisicion', 100)->set('form_valor_residual', 150)
            ->set('form_fecha_adquisicion', now()->addDay()->toDateString())->call('registrarActivo')
            ->assertHasErrors(['form_valor_residual', 'form_fecha_adquisicion']);
        $this->assertDatabaseCount('activos_fijos', 0);
        $form->set('form_valor_residual', 10)->set('form_fecha_adquisicion', now()->toDateString())->call('registrarActivo')->assertHasNoErrors();
        $activo = ActivoFijo::first();
        $this->assertNotNull($activo);
        $form->call('abrirModalBaja', $activo->id)->set('baja_motivo', 'inventado')->call('confirmarBaja')->assertHasErrors('baja_motivo');
        $this->assertSame('activo', $activo->fresh()->estado);
    }

    public function test_formularios_publican_patrones_y_no_prellenan_contrasenas(): void
    {
        $this->get('/clientes/create')->assertOk()->assertSee('type="tel"', false)->assertSee('inputmode="numeric"', false)->assertSee('La Unión');
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertDontSee('value="admin123"', false);
    }

    public function test_filtro_agotados_acepta_checkbox_valido_y_conserva_la_busqueda(): void
    {
        $unidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND']);
        foreach (['Objetivo agotado', 'Otro agotado'] as $i => $nombre) {
            Producto::create(['codigo' => 'AG-'.$i, 'nombre' => $nombre, 'unidad_medida_id' => $unidad->id]);
        }
        Livewire::test(InventarioDashboard::class)->set('mostrarAgotados', true)
            ->assertHasNoErrors()->assertSee('Objetivo agotado')->assertSee('Otro agotado')
            ->set('buscar', 'Objetivo')->assertHasNoErrors()->assertSee('Objetivo agotado')->assertDontSee('Otro agotado')
            ->set('buscar', ['no es texto'])->assertHasErrors('buscar');
    }
}
