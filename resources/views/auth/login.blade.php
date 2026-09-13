<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — ERP Modular</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background: linear-gradient(135deg, var(--primary-900) 0%, var(--primary-950) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface-glass);
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid var(--surface-glass-border);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-xl);
            animation: slideUp var(--transition-base) ease-out;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
            border-radius: var(--radius-lg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .login-title {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            color: var(--neutral-300);
            font-size: 0.875rem;
        }
        .login-form .form-label {
            color: var(--neutral-200);
        }
        .login-form .form-input {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .login-form .form-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-400);
        }
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">AF</div>
            <h1 class="login-title">Bienvenido</h1>
            <p class="login-subtitle">Sistema de Análisis Financiero ERP</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input id="email" type="email" class="form-input @error('email') error @enderror" name="email" value="{{ old('email') ?? 'admin@erp.sv' }}" required autofocus>
                @error('email')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input id="password" type="password" class="form-input @error('password') error @enderror" name="password" required value="admin123">
                @error('password')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input class="" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-label" for="remember" style="margin-bottom: 0;">
                    Recordarme
                </label>
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                Iniciar Sesión
            </button>
        </form>
    </div>
</body>
</html>
