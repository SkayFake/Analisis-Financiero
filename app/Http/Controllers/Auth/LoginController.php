<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $cuenta = 'login:cuenta:'.hash('sha256', Str::lower(trim($credentials['email'])));
        $ip = 'login:ip:'.hash('sha256', (string) $request->ip());
        foreach ([$cuenta => 5, $ip => 20] as $clave => $limite) {
            if (RateLimiter::tooManyAttempts($clave, $limite)) {
                return response('Demasiados intentos de acceso. Intente nuevamente más tarde.', 429)
                    ->header('Retry-After', RateLimiter::availableIn($clave));
            }
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($cuenta);
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        RateLimiter::hit($cuenta, 60);
        RateLimiter::hit($ip, 60);

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
