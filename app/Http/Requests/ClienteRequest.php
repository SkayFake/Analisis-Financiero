<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Rules\DuiSalvador;
use App\Rules\NitSalvador;
use App\Rules\NrcSalvador;
use App\Rules\TelefonoSalvador;
use App\Support\DatosSalvador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClienteRequest extends FormRequest
{
    public const JURIDICOS = ['nombre_comercial', 'giro', 'representante_legal', 'dui_representante', 'balance_general', 'estado_resultados', 'fecha_balance', 'numero_empleados', 'fecha_constitucion'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $datos = DatosSalvador::normalizar($this->all());
        foreach (['ingresos', 'egresos'] as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] === null) {
                $datos[$campo] = 0;
            }
        }
        $this->merge($datos);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $tipo = $cliente instanceof Cliente ? $cliente->tipo : $this->input('tipo');
        $rules = [
            'tipo' => $cliente instanceof Cliente ? ['sometimes', 'required', Rule::in([$cliente->tipo])] : ['required', Rule::in(['natural', 'juridica'])],
            'nombre' => ['required', 'string', 'max:255', 'regex:/[\p{L}]/u', 'not_regex:/[<>\x00-\x1F\x7F]/u'],
            'direccion' => 'nullable|string|max:2000',
            'departamento' => ['nullable', Rule::in(DatosSalvador::DEPARTAMENTOS)],
            'municipio' => 'nullable|string|max:100|regex:/[\p{L}]/u',
            'telefono' => ['bail', 'nullable', 'string', new TelefonoSalvador],
            'celular' => ['bail', 'nullable', 'string', new TelefonoSalvador(soloCelular: true)],
            'email' => 'nullable|string|email:rfc|max:255',
            'dui' => ['bail', 'nullable', 'string', new DuiSalvador, Rule::unique('clientes', 'dui')->ignore($cliente instanceof Cliente ? $cliente->id : null)],
            'nit' => ['bail', 'nullable', 'string', new NitSalvador(permiteDui: $tipo === 'natural')],
            'nrc' => ['bail', 'nullable', 'string', new NrcSalvador],
            'estado_civil' => ['nullable', Rule::in(['soltero', 'casado', 'divorciado', 'viudo', 'union_libre'])],
            'lugar_trabajo' => 'nullable|string|max:255',
            'ingresos' => 'nullable|numeric|min:0|max:999999999999.99|decimal:0,2',
            'egresos' => 'nullable|numeric|min:0|max:999999999999.99|decimal:0,2',
            'zona_id' => ['nullable', 'integer', Rule::exists('zonas', 'id')->where('activa', true)],
            'cartera_id' => ['nullable', 'integer', Rule::exists('carteras', 'id')->where('activa', true)],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('vendedores', 'id')->where('activo', true)],
        ];
        if ($tipo === 'juridica') {
            $rules += [
                'nombre_comercial' => 'nullable|string|max:255',
                'giro' => 'nullable|string|max:255',
                'representante_legal' => 'nullable|string|max:255',
                'dui_representante' => ['bail', 'nullable', 'string', new DuiSalvador],
                'fecha_balance' => 'nullable|date_format:Y-m-d|before_or_equal:today',
                'fecha_constitucion' => 'nullable|date_format:Y-m-d|before_or_equal:today',
                'numero_empleados' => 'nullable|integer|min:0|max:2147483647',
            ];
            foreach (['balance_general' => ClienteJuridico::estructuraBalanceGeneral(), 'estado_resultados' => ClienteJuridico::estructuraEstadoResultados()] as $campo => $estructura) {
                $rules[$campo] = ['nullable', 'array:'.implode(',', array_keys($estructura))];
                foreach ($estructura as $grupo => $valor) {
                    if (is_array($valor)) {
                        $rules["{$campo}.{$grupo}"] = ['sometimes', 'array:'.implode(',', array_keys($valor))];
                        foreach ($valor as $subcampo => $_) {
                            $rules["{$campo}.{$grupo}.{$subcampo}"] = 'sometimes|numeric|max:999999999999.99|decimal:0,2|'.($subcampo === 'utilidades_retenidas' ? 'min:-999999999999.99' : 'min:0');
                        }
                    } else {
                        $rules["{$campo}.{$grupo}"] = 'sometimes|numeric|min:0|max:999999999999.99|decimal:0,2';
                    }
                }
            }
        } else {
            foreach (self::JURIDICOS as $campo) {
                $rules[$campo] = 'prohibited';
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (['dui', 'nit'] as $campo) {
                $valor = $this->input($campo);
                if (is_string($valor) && $valor !== '' && ! $validator->errors()->has($campo)) {
                    $digitos = str_replace('-', '', $valor);
                    $query = Cliente::withTrashed()->where(function ($query) use ($campo, $digitos) {
                        $query->whereRaw("replace({$campo}, '-', '') = ?", [$digitos]);
                        if (strlen($digitos) === 9) {
                            $otro = $campo === 'nit' ? 'dui' : 'nit';
                            $query->orWhereRaw("replace({$otro}, '-', '') = ?", [$digitos]);
                        }
                    });
                    if ($this->route('cliente') instanceof Cliente) {
                        $query->whereKeyNot($this->route('cliente')->id);
                    }
                    if ($query->exists()) {
                        $validator->errors()->add($campo, 'Este identificador ya está registrado en otro cliente.');
                    }
                }
            }
            $nit = $this->input('nit');
            if (is_string($nit) && strlen($nit) === 10 && $this->filled('dui') && $nit !== $this->input('dui')) {
                $validator->errors()->add('nit', 'El NIT homologado debe coincidir con el DUI del cliente.');
            }
            if ($this->filled('fecha_balance') && $this->filled('fecha_constitucion') && ! $validator->errors()->hasAny(['fecha_balance', 'fecha_constitucion']) && $this->input('fecha_balance') < $this->input('fecha_constitucion')) {
                $validator->errors()->add('fecha_balance', 'La fecha del balance no puede ser anterior a la constitución.');
            }
        }];
    }
}
