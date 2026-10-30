<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class PagoController extends Controller
{
    public function recibo(Credito $credito, Pago $pago)
    {
        Gate::authorize('view', $credito);
        abort_unless($pago->credito_id === $credito->id, 404);
        $pago->load(['aplicaciones.cuota', 'cuota', 'recibidoPor']);
        $credito->load(['cliente' => fn ($query) => $query->withTrashed(), 'venta']);

        return Pdf::loadView('pagos.recibo', compact('credito', 'pago'))
            ->setPaper('a4')->stream('recibo_'.$pago->id.'.pdf');
    }
}
