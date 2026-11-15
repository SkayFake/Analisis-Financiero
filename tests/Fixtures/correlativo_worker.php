<?php

use App\Services\CorrelativoService;
use Illuminate\Contracts\Console\Kernel;

// Solo lo ejecuta la prueba de concurrencia contra su base PostgreSQL desechable.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_testing')) {
    exit(2);
}
$numeros = [];
for ($i = 0; $i < 25; $i++) {
    $numeros[] = app(CorrelativoService::class)->siguiente($argv[1]);
}
echo json_encode($numeros);
