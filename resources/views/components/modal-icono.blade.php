@props(['tipo'])

<span class="modal-formulario__icono" aria-hidden="true">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        @switch($tipo)
            @case('activo')
                <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v8l9 5 9-5V8m-9 5v8"/>
                @break
            @case('baja')
                <path d="M4 8h16v12H4zm-1-4h18v4H3zm6 8h6"/>
                @break
            @case('vendedor')
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20a8 8 0 0 1 16 0"/>
                @break
            @case('catalogo')
                <path d="M4 6h16M4 12h16M4 18h16m-11-14v4m6 2v4m-5 2v4"/>
                @break
            @case('pago')
                <rect x="3" y="6" width="18" height="12" rx="2"/>
                <circle cx="12" cy="12" r="2.5"/>
                @break
            @case('aprobacion')
                <path d="m5 12 4 4L19 6"/>
                @break
            @case('rechazo')
                <path d="m6 6 12 12M18 6 6 18"/>
                @break
            @default
                <path d="M12 8v4m0 4h.01M10.3 4.8 3.1 17.2A1.9 1.9 0 0 0 4.8 20h14.4a1.9 1.9 0 0 0 1.7-2.8L13.7 4.8a2 2 0 0 0-3.4 0Z"/>
        @endswitch
    </svg>
</span>
