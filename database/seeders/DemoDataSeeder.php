<?php

namespace Database\Seeders;

use App\Models\Cartera;
use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Models\CondicionCredito;
use App\Models\Credito;
use App\Models\PoliticaCobro;
use App\Models\Vendedor;
use App\Models\Zona;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Usuarios ─────────────────────────────────────────
        $admin = User::create([
            'name' => 'Administrador ERP',
            'email' => 'admin@erp.sv',
            'password' => Hash::make('admin123'),
        ]);
        $admin->assignRole('super-admin');

        $gerente = User::create([
            'name' => 'Gerente Cobros',
            'email' => 'gerente@erp.sv',
            'password' => Hash::make('gerente123'),
        ]);
        $gerente->assignRole('cobros-manager');

        $agente = User::create([
            'name' => 'Agente Cobros',
            'email' => 'agente@erp.sv',
            'password' => Hash::make('agente123'),
        ]);
        $agente->assignRole('cobros-agent');

        // ─── Zonas ────────────────────────────────────────────
        $zonas = [];
        $zonasData = [
            ['codigo' => 'ZN-SS', 'nombre' => 'San Salvador'],
            ['codigo' => 'ZN-LL', 'nombre' => 'La Libertad'],
            ['codigo' => 'ZN-SM', 'nombre' => 'San Miguel'],
            ['codigo' => 'ZN-SA', 'nombre' => 'Santa Ana'],
            ['codigo' => 'ZN-SO', 'nombre' => 'Sonsonate'],
        ];
        foreach ($zonasData as $z) {
            $zonas[] = Zona::create($z);
        }

        // ─── Vendedores ───────────────────────────────────────
        $vendedores = [];
        $vendedoresData = [
            ['codigo' => 'VND-001', 'nombre' => 'Carlos Martínez', 'telefono' => '7890-1234', 'meta_mensual' => 15000, 'zona_id' => $zonas[0]->id],
            ['codigo' => 'VND-002', 'nombre' => 'María López', 'telefono' => '7891-5678', 'meta_mensual' => 12000, 'zona_id' => $zonas[1]->id],
            ['codigo' => 'VND-003', 'nombre' => 'José Hernández', 'telefono' => '7892-9012', 'meta_mensual' => 10000, 'zona_id' => $zonas[2]->id],
        ];
        foreach ($vendedoresData as $v) {
            $vendedores[] = Vendedor::create($v);
        }

        // ─── Carteras ─────────────────────────────────────────
        $carteras = [];
        $carteras[] = Cartera::create(['codigo' => 'CAR-001', 'nombre' => 'Cartera Principal', 'vendedor_id' => $vendedores[0]->id]);
        $carteras[] = Cartera::create(['codigo' => 'CAR-002', 'nombre' => 'Cartera Comercial', 'vendedor_id' => $vendedores[1]->id]);
        $carteras[] = Cartera::create(['codigo' => 'CAR-003', 'nombre' => 'Cartera Oriente', 'vendedor_id' => $vendedores[2]->id]);

        // ─── Política de Cobro ────────────────────────────────
        $politica = PoliticaCobro::create([
            'nombre' => 'Política Estándar',
            'descripcion' => 'Política de cobro por defecto',
            'dias_gracia' => 5,
            'dias_cobro_30' => 30,
            'dias_cobro_60' => 60,
            'interes_moratorio' => 12.0000,
            'dias_incobrable' => 180,
            'activa' => true,
        ]);

        // ─── Condiciones de Crédito Comercial (creadas por CondicionCreditoSeeder) ───
        // Las condiciones se crean en CondicionCreditoSeeder que corre después de este seeder.
        // Los créditos de demo se crean en CondicionCreditoSeeder una vez que las condiciones existen.

        // ─── Clientes Naturales ───────────────────────────────
        $departamentos = ['San Salvador', 'La Libertad', 'San Miguel', 'Santa Ana', 'Sonsonate'];
        $estadosCiviles = ['soltero', 'casado', 'divorciado', 'viudo', 'union_libre'];
        $nombres = [
            'Juan Carlos Pérez', 'Ana María García', 'Roberto Alejandro Flores',
            'Carmen Elena Rivas', 'Pedro Antonio López', 'María José Martínez',
            'Diego Fernando Hernández', 'Claudia Patricia Cruz', 'Ricardo Arturo Vásquez',
            'Lucía Fernanda Portillo', 'Manuel Ernesto Ayala', 'Sandra Isabel Mejía',
            'Francisco Javier Romero', 'Patricia Guadalupe Orellana', 'Luis Alberto Recinos',
            'Rosa María Campos', 'Edgar Mauricio Solano', 'Andrea Carolina Figueroa',
            'Óscar Armando Pineda', 'Karla Sofía Castillo', 'Fernando José Guevara',
            'Martha Cecilia Quintanilla', 'Carlos Eduardo Rodríguez', 'Silvia Elena Tobar',
            'Rafael Antonio Serrano', 'Gabriela Melissa Bonilla', 'José Roberto Ventura',
            'Ana Victoria Escalante', 'Miguel Ángel Zaldívar', 'Lorena Patricia Monge',
            'David Ernesto Palacios', 'Teresa del Carmen Amaya', 'Julio César Cortez',
            'Margarita Elena Argueta', 'Henry Alexander Sorto',
        ];

        $clientesNaturales = [];
        for ($i = 0; $i < 35; $i++) {
            $zonaIdx = $i % count($zonas);
            $clientesNaturales[] = Cliente::create([
                'codigo' => Cliente::generarCodigo('natural'),
                'tipo' => 'natural',
                'nombre' => $nombres[$i],
                'direccion' => 'Col. Ejemplo #' . ($i + 100) . ', ' . $departamentos[$zonaIdx],
                'departamento' => $departamentos[$zonaIdx],
                'telefono' => '2' . rand(200, 299) . '-' . rand(1000, 9999),
                'celular' => '7' . rand(000, 999) . '-' . rand(1000, 9999),
                'dui' => sprintf('%08d-%d', rand(10000000, 99999999), rand(0, 9)),
                'nit' => sprintf('%04d-%06d-%03d-%d', rand(100, 999), rand(100000, 999999), rand(100, 999), rand(0, 9)),
                'estado_civil' => $estadosCiviles[array_rand($estadosCiviles)],
                'lugar_trabajo' => 'Empresa ' . chr(65 + ($i % 26)),
                'ingresos' => rand(500, 3000) + (rand(0, 99) / 100),
                'egresos' => rand(200, 1200) + (rand(0, 99) / 100),
                'zona_id' => $zonas[$zonaIdx]->id,
                'cartera_id' => $carteras[$i % count($carteras)]->id,
                'vendedor_id' => $vendedores[$i % count($vendedores)]->id,
            ]);
        }

        // ─── Clientes Jurídicos ───────────────────────────────
        $empresas = [
            ['nombre' => 'Comercial El Salvador S.A. de C.V.', 'giro' => 'Comercio al por mayor'],
            ['nombre' => 'Distribuidora Central S.A.', 'giro' => 'Distribución'],
            ['nombre' => 'Tecnología Avanzada S.A. de C.V.', 'giro' => 'Servicios tecnológicos'],
            ['nombre' => 'Grupo Agrícola del Pacífico', 'giro' => 'Agricultura'],
            ['nombre' => 'Constructora Cuscatlán S.A.', 'giro' => 'Construcción'],
            ['nombre' => 'Farmacia San Martín S.A.', 'giro' => 'Farmacéutica'],
            ['nombre' => 'Textiles Centroamericanos S.A.', 'giro' => 'Textil'],
            ['nombre' => 'Alimentos Del Valle S.A. de C.V.', 'giro' => 'Industria alimentaria'],
            ['nombre' => 'Transporte Rápido S.A.', 'giro' => 'Transporte'],
            ['nombre' => 'Servicios Financieros Express S.A.', 'giro' => 'Servicios financieros'],
            ['nombre' => 'Importadora del Istmo S.A.', 'giro' => 'Importación'],
            ['nombre' => 'Hotelería Premium S.A. de C.V.', 'giro' => 'Hotelería'],
            ['nombre' => 'Repuestos Automotrices S.A.', 'giro' => 'Automotriz'],
            ['nombre' => 'Plásticos Industriales S.A.', 'giro' => 'Manufactura'],
            ['nombre' => 'Consultoría Empresarial S.A.', 'giro' => 'Consultoría'],
        ];

        $clientesJuridicos = [];
        foreach ($empresas as $idx => $emp) {
            $zonaIdx = $idx % count($zonas);
            $cliente = Cliente::create([
                'codigo' => Cliente::generarCodigo('juridica'),
                'tipo' => 'juridica',
                'nombre' => $emp['nombre'],
                'direccion' => 'Blvd. Empresarial #' . ($idx + 500) . ', ' . $departamentos[$zonaIdx],
                'departamento' => $departamentos[$zonaIdx],
                'telefono' => '2' . rand(500, 599) . '-' . rand(1000, 9999),
                'nit' => sprintf('%04d-%06d-%03d-%d', rand(100, 999), rand(100000, 999999), rand(100, 999), rand(0, 9)),
                'nrc' => sprintf('%06d-%d', rand(100000, 999999), rand(0, 9)),
                'ingresos' => rand(10000, 100000),
                'egresos' => rand(5000, 50000),
                'zona_id' => $zonas[$zonaIdx]->id,
                'cartera_id' => $carteras[$idx % count($carteras)]->id,
                'vendedor_id' => $vendedores[$idx % count($vendedores)]->id,
            ]);

            // Datos financieros del cliente jurídico
            $bg = ClienteJuridico::estructuraBalanceGeneral();
            $bg['activo_corriente']['efectivo'] = rand(5000, 50000);
            $bg['activo_corriente']['cuentas_por_cobrar'] = rand(10000, 80000);
            $bg['activo_corriente']['inventarios'] = rand(5000, 40000);
            $bg['activo_no_corriente']['propiedad_planta_equipo'] = rand(50000, 300000);
            $bg['activo_no_corriente']['depreciacion_acumulada'] = rand(5000, 30000);
            $bg['pasivo_corriente']['cuentas_por_pagar'] = rand(5000, 40000);
            $bg['pasivo_corriente']['prestamos_corto_plazo'] = rand(0, 20000);
            $bg['pasivo_no_corriente']['prestamos_largo_plazo'] = rand(10000, 100000);
            $bg['patrimonio']['capital_social'] = rand(20000, 200000);
            $bg['patrimonio']['reserva_legal'] = rand(1000, 10000);
            $bg['patrimonio']['utilidades_retenidas'] = rand(5000, 50000);

            $er = ClienteJuridico::estructuraEstadoResultados();
            $er['ingresos_operacionales'] = rand(100000, 500000);
            $er['costo_ventas'] = (int) ($er['ingresos_operacionales'] * (rand(40, 70) / 100));
            $er['gastos_operacionales'] = rand(10000, 50000);
            $er['gastos_administrativos'] = rand(5000, 30000);
            $er['gastos_financieros'] = rand(1000, 15000);
            $er['otros_ingresos'] = rand(0, 10000);
            $er['impuesto_renta'] = rand(2000, 20000);

            $datosJuridicos = ClienteJuridico::create([
                'cliente_id'          => $cliente->id,
                'nombre_comercial'    => $emp['nombre'],
                'giro'                => $emp['giro'],
                'representante_legal' => $nombres[$idx] ?? 'Representante Legal',
                'balance_general'     => $bg,
                'estado_resultados'   => $er,
                'fecha_balance'       => now()->subMonths(rand(1, 6)),
                'numero_empleados'    => rand(5, 200),
                'fecha_constitucion'  => now()->subYears(rand(2, 20)),
            ]);

            // Calcular ratios automáticamente
            $datosJuridicos->recalcularRatios();

            $clientesJuridicos[] = $cliente;
        }

        $this->command->info('✅ Demo data seeded: 3 users, 5 zones, 3 sellers, 50 clients.');
        $this->command->info('   Las condiciones de crédito se crearán en CondicionCreditoSeeder.');
    }
}
