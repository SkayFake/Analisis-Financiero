<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — El Tablazo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--surface-bg);
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(155, 107, 50, 0.07) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(46, 95, 110, 0.05) 0%, transparent 40%);
        }
        .login-wrap {
            width: 100%;
            max-width: 440px;
        }
        .login-card {
            padding: 2.5rem;
            background: var(--surface-card);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border-top: 4px solid var(--primary-600);
        }
        .login-logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .login-logo-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border-radius: var(--radius-md);
        }
        .login-logo-text {
            font-family: var(--font-heading);
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-800);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .login-caption {
            color: var(--neutral-500);
            font-size: .7rem;
            letter-spacing: .18em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .login-divider {
            border: none;
            border-top: 1px solid var(--neutral-200);
            margin: 1.5rem 0;
        }
        .login-title {
            font-size: 1.375rem;
            font-family: var(--font-heading);
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 0.375rem;
        }
        .login-subtitle {
            color: var(--neutral-500);
            font-size: .875rem;
            margin-bottom: 1.75rem;
        }
        .login-btn { width: 100%; margin-top: 1rem; }
        .login-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .login-remember input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-600);
            cursor: pointer;
        }
        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: .75rem;
            color: var(--neutral-400);
        }
        @media (max-width: 480px) { .login-card { padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="login-wrap">
        <main class="login-card">
            <div class="login-logo-wrap">
                <img src="{{ asset('imagenes/logo.png') }}" alt="El Tablazo" class="login-logo-img">
                <div>
                    <div class="login-logo-text">El Tablazo</div>
                    <div class="login-caption">Mueblería</div>
                </div>
            </div>

            <hr class="login-divider">

            <h1 class="login-title">Bienvenido de nuevo</h1>
            <p class="login-subtitle">Ingresa para gestionar ventas, clientes y cobros.</p>

            <form method="POST" action="{{ route('login') }}" class="login-form">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input id="email" type="email" class="form-input @error('email') error @enderror"
                           name="email" maxlength="255" value="{{ old('email') }}"
                           required autofocus autocomplete="username"
                           placeholder="tu@correo.com">
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" type="password" class="form-input @error('password') error @enderror"
                           name="password" maxlength="4096"
                           required autocomplete="current-password"
                           placeholder="••••••••">
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group login-remember">
                    <input type="checkbox" name="remember" value="1" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-label" for="remember" style="margin-bottom: 0; cursor: pointer;">
                        Recordarme en este equipo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary login-btn">
                    Iniciar Sesión
                </button>
            </form>

            <p class="login-footer">© {{ date('Y') }} El Tablazo · Sistema ERP</p>
        </main>
    </div>
</body>
</html>
