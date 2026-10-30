<?php

namespace Database\Seeders;

use App\Models\Credito;
use App\Models\ExpedienteDocumento;
use App\Models\ExpedienteEvento;
use App\Models\User;
use App\Services\ExpedienteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Comprobantes sintéticos; no contienen copias de documentos de identidad reales. */
class ExpedientesPruebasSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Los comprobantes QA no se cargan en producción.');
        }
        $admin = User::where('email', 'qa.admin@example.test')->firstOrFail();
        $permisos = ['expedientes.view', 'expedientes.upload', 'expedientes.review', 'expedientes.manage'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (['qa-admin', 'qa-gerente'] as $r) {
            Role::where('name', $r)->first()?->givePermissionTo($permisos);
        }
        Role::where('name', 'qa-cobros')->first()?->givePermissionTo(['expedientes.view', 'expedientes.upload']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $pendiente = Credito::where('observaciones', 'like', 'QA-PENDIENTE |%')->firstOrFail();
        $completo = Credito::where('observaciones', 'like', 'QA-CON-FIADOR |%')->firstOrFail();
        $servicio = app(ExpedienteService::class);
        if (! ExpedienteEvento::where('credito_id', $pendiente->id)->where('accion', 'politica_qa')->exists()) {
            $pendiente->update(['expediente_requerido' => true, 'requisitos_expediente' => $servicio->politica($pendiente->cliente), 'dui_verificado' => false]);
            $servicio->evento($pendiente->cliente, $pendiente, null, null, 'politica_qa', null, $pendiente->only(['expediente_requerido', 'requisitos_expediente']), 'Escenario QA pendiente con comprobantes incompletos.', $admin);
        }
        // Rerun no repone archivos borrados ni revisiones hechas por el usuario.
        $crear = function (Credito $credito, string $categoria, string $marca, string $estado = 'validado', $fiador = null, ?string $vencimiento = null, ?ExpedienteDocumento $anterior = null) use ($servicio, $admin) {
            $existente = ExpedienteDocumento::where('notas', $marca)->first();
            if ($existente) {
                return $existente;
            }
            $ruta = tempnam(sys_get_temp_dir(), 'qa-comprobante-');
            file_put_contents($ruta, Pdf::loadHTML('<h1>DOCUMENTO SINTÉTICO DE PRUEBA</h1><p>No es documento de identidad, constancia oficial, contrato ni fianza válidos.</p><p>'.e($marca).'</p>')->output());
            try {
                $datos = ['categoria' => $categoria, 'notas' => $marca, 'fecha_emision' => today()->subYear()->toDateString(), 'fecha_vencimiento' => $vencimiento];
                if ($anterior) {
                    $datos += ['reemplaza_id' => $anterior->id, 'motivo' => 'Nueva versión sintética para practicar revisión documental.'];
                }
                $doc = $servicio->subir($credito->cliente, $fiador || in_array($categoria, ['solicitud', 'contrato']) ? $credito : null, $fiador,
                    new UploadedFile($ruta, $marca.'.pdf', 'application/pdf', null, true), $datos, $admin);
                if ($estado !== 'pendiente') {
                    $servicio->revisar($doc, $estado, 'Revisión simulada QA; no acredita autenticidad ni cumplimiento legal.', $admin);
                }

                return $doc;
            } finally {
                @unlink($ruta);
            }
        };
        $anterior = $crear($pendiente, 'domicilio', 'QA-DOC-DOMICILIO-RECHAZADO', 'rechazado');
        $crear($pendiente, 'domicilio', 'QA-DOC-DOMICILIO-V2', 'pendiente', anterior: $anterior);
        $crear($pendiente, 'constancia_salario', 'QA-DOC-INGRESOS-VENCIDO', 'pendiente', vencimiento: today()->subDay()->toDateString());
        $crear($pendiente, 'solicitud', 'QA-DOC-SOLICITUD-PENDIENTE', 'pendiente');
        foreach (['dui_completo', 'domicilio', 'estado_cuenta', 'solicitud', 'contrato'] as $categoria) {
            $crear($completo, $categoria, 'QA-DOC-CLIENTE-'.$categoria);
        }
        $fiador = $completo->fiadores()->firstOrFail();
        if (! ExpedienteEvento::where('fiador_id', $fiador->id)->where('accion', 'datos_qa')->exists()) {
            $antes = $fiador->toArray();
            $fiador->update(['ingresos' => 900, 'egresos' => 350, 'lugar_trabajo' => 'Empresa ficticia QA']);
            $servicio->evento($completo->cliente, $completo, $fiador, null, 'datos_qa', $antes, $fiador->toArray(), 'Datos sintéticos complementarios del fiador del escenario QA.', $admin);
        }
        foreach (['dui_completo', 'domicilio', 'constancia_salario', 'fianza'] as $categoria) {
            $crear($completo, $categoria, 'QA-DOC-FIADOR-'.$categoria, fiador: $fiador);
        }
        $this->command?->info('Expedientes QA: PENDIENTE incompleto/rechazado/vencido/versionado y CON-FIADOR con archivo completo de cliente, crédito y fiador.');
    }
}
