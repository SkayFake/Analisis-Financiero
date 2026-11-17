<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionDTE extends Model
{
    protected $casts = ['api_key' => 'encrypted', 'password_api' => 'encrypted', 'password_firmador' => 'encrypted'];

    protected $hidden = ['api_key', 'password_api', 'password_firmador'];

    protected $table = 'configuracion_dte';

    protected $fillable = [
        'nit', 'nrc', 'nombre_comercial', 'actividad_economica',
        'api_key', 'password_api', 'url_firmador', 'password_firmador', 'ambiente',
    ];
}
