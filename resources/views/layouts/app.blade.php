<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ERP Modular - Sistema de Análisis Financiero para El Salvador">

    <title>{{ $title ?? 'Dashboard' }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div class="erp-layout" x-data="{ sidebarCollapsed: false, sidebarMobile: false }">

        {{-- ═══════ SIDEBAR ═══════ --}}
        <aside class="sidebar"
               :class="{ 'collapsed': sidebarCollapsed, 'mobile-open': sidebarMobile }"
               id="sidebar">

            {{-- Brand --}}
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">AF</div>
                <span class="sidebar-brand-text">Análisis Financiero</span>
            </div>

            {{-- Navigation --}}
            <nav class="sidebar-nav">

                {{-- Principal --}}
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Principal</div>
                    <a href="{{ route('dashboard') }}"
                       class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Dashboard</span>
                    </a>
                </div>

                {{-- Cuentas por Cobrar --}}
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Cuentas por Cobrar</div>

                    <a href="{{ route('clientes.index') }}"
                       class="sidebar-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Clientes</span>
                    </a>

                    <a href="{{ route('creditos.index') }}"
                       class="sidebar-link {{ request()->routeIs('creditos.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Créditos</span>
                        @php $creditosVencidos = \App\Models\Credito::vencidos()->count(); @endphp
                        @if($creditosVencidos > 0)
                            <span class="sidebar-link-badge">{{ $creditosVencidos }}</span>
                        @endif
                    </a>

                    <a href="{{ route('cobros.dashboard') }}"
                       class="sidebar-link {{ request()->routeIs('cobros.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
                                <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Cobros</span>
                    </a>
                </div>

                {{-- Módulos Futuros --}}
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Módulos</div>

                    <a href="{{ route('inventario.dashboard') }}" class="sidebar-link {{ request()->routeIs('inventario.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                <path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Inventarios</span>
                    </a>

                    <a href="{{ route('facturacion.pos') }}" class="sidebar-link {{ request()->routeIs('facturacion.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                                <path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Facturación DTE</span>
                    </a>

                    <a href="{{ route('activos-fijos.dashboard') }}" class="sidebar-link {{ request()->routeIs('activos-fijos.*') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-text">Activo Fijo</span>
                    </a>
                </div>
            </nav>

            {{-- Toggle Button --}}
            <div class="sidebar-toggle">
                <button class="sidebar-toggle-btn" @click="sidebarCollapsed = !sidebarCollapsed" title="Colapsar menú">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                </button>
            </div>
        </aside>

        {{-- ═══════ MAIN CONTENT ═══════ --}}
        <div class="erp-main" :class="{ 'sidebar-collapsed': sidebarCollapsed }">

            {{-- Header --}}
            <header class="header" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
                <div class="header-left">
                    {{-- Mobile toggle --}}
                    <button class="header-btn" @click="sidebarMobile = !sidebarMobile"
                            style="display: none;" id="mobile-toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>

                    {{-- Breadcrumbs --}}
                    <nav class="header-breadcrumbs">
                        <a href="{{ route('dashboard') }}">Inicio</a>
                        @if(isset($breadcrumbs))
                            @foreach($breadcrumbs as $crumb)
                                <span class="separator">/</span>
                                @if($loop->last)
                                    <span class="current">{{ $crumb['label'] }}</span>
                                @else
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                @endif
                            @endforeach
                        @endif
                    </nav>
                </div>

                <div class="header-right">
                    {{-- Search --}}
                    <div class="header-search">
                        <svg class="header-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                        </svg>
                        <input type="text" placeholder="Buscar cliente, crédito..." id="global-search">
                    </div>

                    {{-- Notifications --}}
                    <button class="header-btn" title="Notificaciones">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                        </svg>
                        <span class="notification-dot"></span>
                    </button>

                    {{-- User Menu --}}
                    @auth
                    <div class="header-user" x-data="{ open: false }" @click="open = !open">
                        <div class="header-avatar">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="header-user-name">{{ auth()->user()->name }}</div>
                            <div class="header-user-role">{{ auth()->user()->roles->first()?->name ?? 'Usuario' }}</div>
                        </div>
                    </div>
                    @endauth
                </div>
            </header>

            {{-- Page Content --}}
            <main class="erp-content">
                {{-- Flash Messages --}}
                @if(session('success'))
                    <div class="alert alert-success animate-slideUp" x-data="{ show: true }" x-show="show"
                         x-init="setTimeout(() => show = false, 5000)" x-transition>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger animate-slideUp" x-data="{ show: true }" x-show="show"
                         x-init="setTimeout(() => show = false, 5000)" x-transition>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Toast Container --}}
    <div class="toast-container" id="toast-container"></div>

    @livewireScripts
</body>
</html>
