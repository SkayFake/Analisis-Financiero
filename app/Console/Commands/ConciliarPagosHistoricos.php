<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Services\ConciliacionPagosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConciliarPagosHistoricos extends Command
{
    protected $signature = 'creditos:conciliar-pagos {--credito= : ID de un crédito} {--aplicar : Guardar las aplicaciones conciliadas}';

    protected $description = 'Comprueba pagos históricos y reconstruye únicamente las aplicaciones que concilian exactamente';

    public function handle(ConciliacionPagosService $servicio): int
    {
        $bloqueados = 0;
        $query = Credito::withTrashed()->whereHas('pagos', fn ($q) => $q->whereDoesntHave('aplicaciones'));
        if ($this->option('credito') !== null) {
            if (! ctype_digit((string) $this->option('credito')) || (int) $this->option('credito') < 1) {
                $this->error('El ID del crédito debe ser un entero positivo.');

                return self::INVALID;
            }
            $query->whereKey($this->option('credito'));
        }
        foreach ($query->lazyById() as $credito) {
            DB::beginTransaction();
            try {
                $cantidad = $servicio->conciliar($credito);
                if ($this->option('aplicar')) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
                $this->info("{$credito->numero}: {$cantidad} recibos conciliables".($this->option('aplicar') ? ' (guardados).' : ' (sin guardar).'));
            } catch (InvalidArgumentException $e) {
                DB::rollBack();
                $bloqueados++;
                $this->error($e->getMessage());
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return $bloqueados ? self::FAILURE : self::SUCCESS;
    }
}
