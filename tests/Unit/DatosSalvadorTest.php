<?php

namespace Tests\Unit;

use App\Rules\DuiSalvador;
use App\Rules\NitSalvador;
use App\Rules\NrcSalvador;
use App\Rules\TelefonoSalvador;
use App\Support\DatosSalvador;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatosSalvadorTest extends TestCase
{
    private function acepta(ValidationRule $regla, mixed $valor): bool
    {
        $errores = [];
        $regla->validate('campo', $valor, function ($mensaje) use (&$errores) {
            $errores[] = $mensaje;
        });

        return $errores === [];
    }

    #[DataProvider('telefonos')]
    public function test_telefonos(mixed $valor, bool $valido, bool $celular = false): void
    {
        $this->assertSame($valido, $this->acepta(new TelefonoSalvador($celular), $valor));
    }

    public static function telefonos(): array
    {
        return [
            ['2234-5678', true], ['22345678', true], ['6123-4567', true], ['71234567', true],
            ['+503 7123-4567', true], ['+50371234567', true], ['+503 7123 4567', true],
            ['+502 71234567', false], ['9123-4567', false], ['12345678', false], ['7123456', false],
            ['712345678', false], ['abc71234567', false], ['7123-4567 ext. 5', false], ['911', false],
            ['00000000', false], ['7123--4567', false], ['7123.4567', false], [22345678, false], [[], false],
            ['2234-5678', false, true], ['61234567', true, true], ['+503 7123-4567', true, true],
        ];
    }

    #[DataProvider('duis')]
    public function test_dui(mixed $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->acepta(new DuiSalvador, $valor));
    }

    public static function duis(): array
    {
        return [
            ['01234567-8', true], ['012345678', true], ['12345678-4', true],
            ['01234567-9', false], ['00000000-0', false], ['000000000', false],
            ['12345678-9', false], ['0123456-78', false], ['abc012345678', false],
            ['0123456789', false], ['0123456-7', false], [123456784, false], [[], false],
        ];
    }

    #[DataProvider('nits')]
    public function test_nit(mixed $valor, bool $valido, bool $permiteDui = true): void
    {
        $this->assertSame($valido, $this->acepta(new NitSalvador($permiteDui), $valor));
    }

    public static function nits(): array
    {
        return [
            ['0614-010101-001-3', true], ['06140101010013', true], ['0614-010101-101-0', true],
            ['0614-010100-100-0', true], ['0614-010101-001-4', false], ['0614-010101-101-1', false],
            ['0614-310201-001-3', false], ['0000-010101-001-3', false], ['0000-000000-000-0', false],
            ['0614-010101-000-3', false], ['0614010101001', false], ['NIT 06140101010013', false],
            ['01234567-8', true], ['012345678', true], ['01234567-9', false], ['01234567-8', false, false],
        ];
    }

    #[DataProvider('nrcs')]
    public function test_nrc(mixed $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->acepta(new NrcSalvador, $valor));
    }

    public static function nrcs(): array
    {
        return [['123456-7', true], ['1234567', true], ['12345678', true], ['1', true], ['00000001', true], ['00000000', false], ['123456789', false], ['123-456-7', false], ['NRC123', false], [123, false]];
    }

    public function test_normalizacion_no_elimina_letras_ni_inventa_digitos(): void
    {
        $this->assertSame([
            'telefono' => '2234-5678', 'celular' => '7123-4567', 'dui' => '01234567-8',
            'nit' => '0614-010101-101-0', 'nrc' => '1234567', 'departamento' => 'La Unión',
            'fiador' => ['telefono' => '6123-4567'],
        ], DatosSalvador::normalizar([
            'telefono' => ' +503 2234-5678 ', 'celular' => '71234567', 'dui' => '012345678',
            'nit' => '06140101011010', 'nrc' => '123456-7', 'departamento' => 'la union',
            'fiador' => ['telefono' => '+50361234567'],
        ]));
        foreach (['telefono' => 'abc22345678', 'dui' => 'DUI012345678', 'nit' => '0614010101001'] as $campo => $valor) {
            $this->assertSame($valor, DatosSalvador::campo($campo, $valor));
        }
    }
}
