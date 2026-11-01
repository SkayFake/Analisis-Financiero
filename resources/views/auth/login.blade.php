<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — El Tablazo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { min-height: 100dvh; display: grid; place-items: center; padding: 1.5rem; background: var(--surface-bg); }
        .login-card { width: 100%; max-width: 440px; padding: 2.5rem; background: var(--surface-card); border: 1px solid var(--neutral-200); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border-top: 4px solid var(--primary-500); }
        .login-header { margin-bottom: 2rem; }
        .login-logo { color: var(--primary-800); font-size: 1.8rem; font-weight: 800; letter-spacing: -.05em; }
        .login-caption { color: var(--neutral-600); font-size: .75rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 2rem; }
        .login-title { font-size: 1.5rem; margin-bottom: .5rem; }
        .login-subtitle { color: var(--neutral-600); font-size: .875rem; }
        .login-btn { width: 100%; margin-top: 1rem; }
        @media (max-width: 480px) { .login-card { padding: 1.5rem; } }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="login-header">
            <div class="login-logo">El Tablazo</div>
            <p class="login-caption">Mueblería</p>
            <h1 class="login-title">Bienvenido</h1>
            <p class="login-subtitle">Ingresa para gestionar ventas, clientes y cobros.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input id="email" type="email" class="form-input @error('email') error @enderror" name="email" maxlength="255" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input id="password" type="password" class="form-input @error('password') error @enderror" name="password" maxlength="4096" required autocomplete="current-password">
                @error('password')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input class="" type="checkbox" name="remember" value="1" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-label" for="remember" style="margin-bottom: 0;">
                    Recordarme
                </label>
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                Iniciar Sesión
            </button>
        </form>
    </main>
</body>
</html>
