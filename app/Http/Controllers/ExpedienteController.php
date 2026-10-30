<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\ExpedienteDocumento;
use App\Models\ExpedienteEvento;
use App\Models\Fiador;
use App\Services\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpedienteController extends Controller
{
    public function __construct(private ExpedienteService $expedientes) {}

    private function permiso(Request $request, string $accion = 'view'): void
    {
        $this->expedientes->permitir($request->user(), $accion);
    }

    public function cliente(Request $request, Cliente $cliente)
    {
        return $this->mostrar($request, $cliente);
    }

    public function credito(Request $request, Credito $credito)
    {
        return $this->mostrar($request, $credito->cliente, $credito);
    }

    private function mostrar(Request $request, Cliente $cliente, ?Credito $credito = null)
    {
        $this->permiso($request);
        $secciones = [['titulo' => 'Documentos del cliente', 'ambito' => 'cliente', 'credito' => null, 'fiador' => null]];
        if ($credito) {
            $secciones[] = ['titulo' => 'Documentos de este crédito', 'ambito' => 'credito', 'credito' => $credito, 'fiador' => null];
            foreach ($credito->fiadores()->get() as $fiador) {
                $secciones[] = ['titulo' => 'Fiador: '.$fiador->nombre, 'ambito' => 'fiador', 'credito' => $credito, 'fiador' => $fiador];
            }
        }
        foreach ($secciones as &$seccion) {
            $seccion['categorias'] = $this->expedientes->categorias($seccion['ambito']);
            $seccion['documentos'] = $this->expedientes->documentos($cliente, $seccion['credito'], $seccion['fiador'])->with(['autor', 'revisor'])->orderByDesc('id')->get();
            foreach ($seccion['documentos'] as $doc) {
                $doc->setAttribute('aceptable', $this->expedientes->utilizable($doc, $cliente, $seccion['fiador']));
            }
        }
        unset($seccion);
        $lista = $credito ? $this->expedientes->lista($credito) : [];
        $eventos = ExpedienteEvento::where('cliente_id', $cliente->id)
            ->when($credito, fn ($q) => $q->where(fn ($q) => $q->whereNull('credito_id')->orWhere('credito_id', $credito->id)))
            ->orderByDesc('id')->paginate(30);

        return view('expedientes.show', compact('cliente', 'credito', 'secciones', 'lista', 'eventos'));
    }

    public function subirCliente(Request $request, Cliente $cliente)
    {
        return $this->subir($request, $cliente);
    }

    public function subirCredito(Request $request, Credito $credito)
    {
        return $this->subir($request, $credito->cliente, $credito);
    }

    public function subirFiador(Request $request, Credito $credito, Fiador $fiador)
    {
        return $this->subir($request, $credito->cliente, $credito, $fiador);
    }

    private function subir(Request $request, Cliente $cliente, ?Credito $credito = null, ?Fiador $fiador = null)
    {
        $this->permiso($request, 'upload');
        $this->expedientes->contexto($cliente, $credito, $fiador);
        $request->validate(['archivo' => 'required|file|max:'.config('expedientes.max_kb')]);
        $this->expedientes->subir($cliente, $credito, $fiador, $request->file('archivo'), $request->only(['categoria', 'fecha_emision', 'fecha_vencimiento', 'notas', 'reemplaza_id', 'motivo']), $request->user());

        return back()->with('success', 'Comprobante guardado de forma privada. Está pendiente de revisión.');
    }

    private function documento(Cliente $cliente, ExpedienteDocumento $documento): void
    {
        abort_unless((int) $documento->cliente_id === $cliente->id, 404);
    }

    public function descargar(Request $request, Cliente $cliente, ExpedienteDocumento $documento)
    {
        $this->permiso($request);
        $this->documento($cliente, $documento);
        abort_unless($this->expedientes->integro($documento), 409, 'Archivo ausente o alterado; no se puede entregar el comprobante.');
        $this->expedientes->evento($cliente, $documento->credito, $documento->fiador, $documento, 'descarga', null, ['id' => $documento->id, 'version' => $documento->version, 'sha256' => $documento->sha256], 'Descarga autenticada del comprobante.', $request->user());

        return Storage::disk('expedientes')->download($documento->ruta, 'comprobante-'.$documento->id.'-v'.$documento->version.'.'.pathinfo($documento->ruta, PATHINFO_EXTENSION), [
            'Content-Type' => $documento->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    public function revisar(Request $request, Cliente $cliente, ExpedienteDocumento $documento)
    {
        $this->permiso($request, 'review');
        $this->documento($cliente, $documento);
        $datos = $request->validate(['estado' => 'required|string', 'motivo' => 'required|string|min:8|max:2000']);
        $this->expedientes->revisar($documento, $datos['estado'], $datos['motivo'], $request->user());

        return back()->with('success', 'Revisión registrada con responsable y motivo.');
    }

    public function fiador(Request $request, Credito $credito, ?Fiador $fiador = null)
    {
        $this->permiso($request, 'manage');
        $this->expedientes->contexto($credito->cliente, $credito, $fiador);
        $this->expedientes->guardarFiador($credito, $fiador, $request->only(['nombre', 'dui', 'nit', 'telefono', 'direccion', 'lugar_trabajo', 'ingresos', 'egresos', 'observaciones', 'motivo']), $request->user());

        return back()->with('success', 'Datos del fiador registrados en el expediente de este crédito.');
    }
}
