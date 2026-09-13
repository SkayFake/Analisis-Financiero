<div>
    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Punto de Facturación Electrónica (DTE)</h1>
            <p class="page-subtitle">Emisión y firma digital de documentos tributarios autorizados por el Ministerio de Hacienda (El Salvador).</p>
        </div>
        <div class="page-actions">
            @if(count($detalles) > 0)
                <button type="button" wire:click="limpiarCarrito" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Limpiar Canasta</span>
                </button>
            @endif
            <a href="{{ route('inventario.dashboard') }}" class="btn btn-secondary" title="Ver existencias de productos">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7.5 4.27 9 5.15"/>
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="m3.3 7 8.7 5 8.7-5"/>
                    <path d="M12 22V12"/>
                </svg>
                <span>Stock Inventario</span>
            </a>
        </div>
    </div>

    {{-- Notificaciones / Alertas --}}
    @if(session('error'))
        <div class="alert alert-danger" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; background: var(--danger-50); border: 1px solid var(--danger-200); color: var(--danger-700); padding: 1rem 1.25rem; border-radius: var(--radius-md);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div><strong>Error en la operación:</strong> {{ session('error') }}</div>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; background: var(--success-50); border: 1px solid var(--success-200); color: var(--success-700); padding: 1rem 1.25rem; border-radius: var(--radius-md);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    {{-- KPIs Estadísticas del Día --}}
    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        {{-- Total Facturado Hoy --}}
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Ventas Facturadas Hoy</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">${{ number_format($totalHoy, 2) }}</div>
            <div class="stat-card-change up">
                {{ $docsHoy }} documento(s) emitido(s)
            </div>
        </div>

        {{-- DTEs Transmitidos / Procesados --}}
        <div class="stat-card" style="--stat-color: var(--success-600); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">DTEs Transmitidos MH</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">{{ $docsTransmitidos }}</div>
            <div class="stat-card-change up">
                Sello de recepción OK
            </div>
        </div>

        {{-- DTEs en Contingencia --}}
        <div class="stat-card" style="--stat-color: var(--warning-600); --stat-bg: var(--warning-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">En Contingencia / Cola</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">{{ $docsContingencia }}</div>
            <div class="stat-card-change" style="background: var(--warning-50); color: var(--warning-700);">
                Reintento programado
            </div>
        </div>
    </div>

    {{-- Layout Principal POS: 2 Columnas (Buscador y Carrito / Checkout Panel) --}}
    <div style="display: grid; grid-template-columns: minmax(0, 1.9fr) minmax(360px, 1.1fr); gap: 1.5rem; align-items: start;">
        
        {{-- ═════════════════ COLUMNA IZQUIERDA: BUSCADOR Y LISTA DE PRODUCTOS ═════════════════ --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- Buscador con Autocomplete Inteligente --}}
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    
                    {{-- Selector de Bodega de Despacho --}}
                    <div style="min-width: 180px;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 2px;">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Bodega Despacho
                        </label>
                        <select wire:model.live="bodega_id" class="form-select" style="padding: 0.5rem 0.75rem; font-size: 0.8125rem;">
                            @foreach($this->bodegas as $b)
                                <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Campo de Búsqueda --}}
                    <div style="flex: 1; min-width: 250px; position: relative;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Buscar y Añadir Producto</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--neutral-400); pointer-events: none;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </span>
                            <input type="text" 
                                   wire:model.live.debounce.250ms="searchProducto" 
                                   class="form-input" 
                                   style="padding-left: 2.5rem; font-size: 0.95rem; height: 42px;" 
                                   placeholder="Escribe nombre o código de barra (Ej: Smart TV, ELEC-001)..." 
                                   autofocus>
                        </div>

                        {{-- Dropdown Flotante de Resultados --}}
                        @if(strlen($searchProducto) >= 2)
                            <div style="position: absolute; top: calc(100% + 4px); left: 0; width: 100%; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--neutral-200); box-shadow: 0 12px 30px rgba(0,0,0,0.15); z-index: 100; overflow: hidden; max-height: 380px; overflow-y: auto;">
                                <div style="padding: 0.5rem 0.875rem; background: var(--neutral-50); border-bottom: 1px solid var(--neutral-100); font-size: 0.75rem; font-weight: 600; color: var(--neutral-500); text-transform: uppercase;">
                                    Resultados para "{{ $searchProducto }}"
                                </div>
                                @forelse($this->productos as $p)
                                    @php
                                        $stockBodega = $p->existencias->where('bodega_id', $bodega_id)->sum('cantidad');
                                    @endphp
                                    <div wire:click="agregarProducto({{ $p->id }})" 
                                         style="padding: 0.875rem 1rem; border-bottom: 1px solid var(--neutral-100); cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: background 0.15s ease;"
                                         onmouseover="this.style.background='var(--primary-50)'"
                                         onmouseout="this.style.background='#ffffff'">
                                        <div>
                                            <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                                                <span style="font-family: monospace; color: var(--primary-600); font-weight: 700; margin-right: 0.35rem;">{{ $p->codigo }}</span>
                                                {{ $p->nombre }}
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--neutral-500); margin-top: 0.2rem;">
                                                Precio de Venta: <strong style="color: var(--neutral-800);">${{ number_format($p->precio_venta, 2) }}</strong>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            @if($stockBodega > 0)
                                                <span class="badge badge-success">
                                                    {{ number_format($stockBodega, 2) }} en bodega
                                                </span>
                                            @else
                                                <span class="badge badge-danger">
                                                    Sin stock local
                                                </span>
                                            @endif
                                            <div style="font-size: 0.75rem; color: var(--primary-600); font-weight: 600; margin-top: 0.25rem;">
                                                + Agregar
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div style="padding: 1.5rem; text-align: center; color: var(--neutral-500); font-size: 0.875rem;">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 0.5rem; display: block; opacity: 0.5;">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        No se encontraron productos coincidentes.
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>

                </div>
            </div>

            {{-- Tabla de Ítems en Carrito / Canasta --}}
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="card-header" style="background: #ffffff; border-bottom: 1px solid var(--neutral-100); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-600);">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <h2 class="card-title" style="margin: 0; font-size: 0.95rem;">Detalle de la Factura ({{ count($detalles) }} ítems)</h2>
                    </div>
                    @if(count($detalles) > 0)
                        <span class="badge badge-info">{{ count($detalles) }} producto(s) en lista</span>
                    @endif
                </div>

                <div class="data-table-wrapper" style="border: none; border-radius: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Producto / Concepto</th>
                                <th style="width: 18%; text-align: center;">Cantidad</th>
                                <th style="width: 18%; text-align: right;">Precio Unit.</th>
                                <th style="width: 18%; text-align: right;">Subtotal</th>
                                <th style="width: 6%; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($detalles as $index => $det)
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--neutral-900);">
                                            {{ $det['nombre'] }}
                                        </div>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.2rem;">
                                            <span style="font-family: monospace; font-size: 0.75rem; background: var(--neutral-100); padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--neutral-700);">
                                                {{ $det['codigo'] }}
                                            </span>
                                            @if($det['cantidad'] > $det['stock'])
                                                <span class="badge badge-danger" style="font-size: 0.65rem;">
                                                    Supera stock ({{ number_format($det['stock'], 2) }})
                                                </span>
                                            @else
                                                <span style="font-size: 0.75rem; color: var(--neutral-400);">
                                                    Stock: {{ number_format($det['stock'], 2) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Selector de Cantidad con Botones +/- --}}
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div style="display: inline-flex; align-items: center; border: 1.5px solid var(--neutral-200); border-radius: var(--radius-md); background: #ffffff; overflow: hidden;">
                                            <button type="button" 
                                                    wire:click="cambiarCantidad({{ $index }}, -1)"
                                                    style="border: none; background: transparent; padding: 0.35rem 0.5rem; cursor: pointer; color: var(--neutral-600); line-height: 1;"
                                                    title="Disminuir 1">
                                                -
                                            </button>
                                            <input type="number" 
                                                   wire:model.live.debounce.300ms="detalles.{{$index}}.cantidad" 
                                                   min="0.01" 
                                                   step="1" 
                                                   style="width: 55px; border: none; text-align: center; font-weight: 600; font-size: 0.875rem; outline: none; padding: 0.25rem 0;">
                                            <button type="button" 
                                                    wire:click="cambiarCantidad({{ $index }}, 1)"
                                                    style="border: none; background: transparent; padding: 0.35rem 0.5rem; cursor: pointer; color: var(--neutral-600); line-height: 1;"
                                                    title="Aumentar 1">
                                                +
                                            </button>
                                        </div>
                                    </td>

                                    {{-- Precio Unitario Editable si se requiere --}}
                                    <td style="text-align: right; vertical-align: middle;">
                                        <div style="display: inline-flex; align-items: center; justify-content: flex-end; width: 100%;">
                                            <span style="color: var(--neutral-400); margin-right: 0.25rem; font-size: 0.875rem;">$</span>
                                            <input type="number" 
                                                   wire:model.live.debounce.300ms="detalles.{{$index}}.precio_unitario" 
                                                   min="0" 
                                                   step="0.01" 
                                                   class="form-input" 
                                                   style="width: 85px; text-align: right; padding: 0.35rem 0.5rem; font-size: 0.875rem; font-weight: 600;">
                                        </div>
                                    </td>

                                    {{-- Subtotal de Línea --}}
                                    <td style="text-align: right; font-weight: 700; color: var(--neutral-900); font-size: 0.9375rem; vertical-align: middle;">
                                        ${{ number_format(($det['cantidad'] ?? 0) * ($det['precio_unitario'] ?? 0), 2) }}
                                    </td>

                                    {{-- Botón Eliminar --}}
                                    <td style="text-align: center; vertical-align: middle;">
                                        <button type="button" 
                                                wire:click="removerProducto({{ $index }})" 
                                                class="btn btn-ghost" 
                                                style="color: var(--danger-500); padding: 0.35rem; border-radius: var(--radius-md);" 
                                                title="Quitar ítem">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 3.5rem 1rem; text-align: center;">
                                        <div style="max-width: 320px; margin: 0 auto; color: var(--neutral-400);">
                                            <div style="width: 56px; height: 56px; margin: 0 auto 1rem; border-radius: 50%; background: var(--neutral-100); display: flex; align-items: center; justify-content: center; color: var(--neutral-400);">
                                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                    <circle cx="9" cy="21" r="1"></circle>
                                                    <circle cx="20" cy="21" r="1"></circle>
                                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                                </svg>
                                            </div>
                                            <p style="font-weight: 600; font-size: 0.9375rem; color: var(--neutral-700); margin: 0 0 0.25rem;">La factura está vacía</p>
                                            <p style="font-size: 0.8125rem; margin: 0;">Usa el buscador superior para buscar productos por código o nombre y agregarlos aquí.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Footer de Resumen Rápido dentro de la canasta --}}
                @if(count($detalles) > 0)
                    <div style="padding: 0.875rem 1.25rem; background: var(--neutral-50); border-top: 1px solid var(--neutral-100); display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; color: var(--neutral-600);">
                        <span>Total de unidades: <strong>{{ collect($detalles)->sum('cantidad') }}</strong></span>
                        <span>Subtotal de líneas: <strong style="color: var(--neutral-900); font-size: 0.875rem;">${{ number_format($subtotal, 2) }}</strong></span>
                    </div>
                @endif
            </div>

        </div>

        {{-- ═════════════════ COLUMNA DERECHA: CONFIGURACIÓN DTE & TOTALES ═════════════════ --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- Parámetros Fiscales del DTE --}}
            <div class="card" style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--neutral-100); padding-bottom: 0.75rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-600);">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <path d="M14 2v6h6"/>
                        <path d="M16 13H8"/>
                        <path d="M16 17H8"/>
                        <path d="M10 9H8"/>
                    </svg>
                    <h3 style="font-size: 1rem; font-weight: 700; margin: 0; color: var(--neutral-900);">Datos del DTE</h3>
                </div>

                {{-- Tipo de Documento Tributario --}}
                <div class="form-group">
                    <label class="form-label">Tipo de Documento <span class="required">*</span></label>
                    <select wire:model.live="tipo_documento" class="form-select" style="font-weight: 600;">
                        <option value="01">01 - Factura Consumidor Final (FCF)</option>
                        <option value="03">03 - Comprobante de Crédito Fiscal (CCF)</option>
                        <option value="11">11 - Ticket de Venta (Interno POS)</option>
                        <option value="05">05 - Nota de Crédito (NC)</option>
                        <option value="06">06 - Nota de Débito (ND)</option>
                    </select>
                    <div class="form-hint" style="font-size: 0.75rem;">
                        @if($tipo_documento === '01')
                            <span class="badge badge-info" style="font-size: 0.65rem;">FCF</span> Venta a particulares. IVA (13%) incluido en precio.
                        @elseif($tipo_documento === '03')
                            <span class="badge badge-warning" style="font-size: 0.65rem;">CCF</span> Contribuyentes IVA. Desglosa débito fiscal +13%.
                        @elseif($tipo_documento === '11')
                            <span class="badge badge-neutral" style="font-size: 0.65rem;">Ticket</span> Documento simplificado continuo (80mm).
                        @endif
                    </div>
                </div>

                {{-- Condición de la Operación --}}
                <div class="form-group">
                    <label class="form-label">Condición de Pago <span class="required">*</span></label>
                    <select wire:model.live="condicion_operacion" class="form-select">
                        <option value="1">Contado (Efectivo / Transferencia)</option>
                        <option value="2">Crédito Comercial (Genera CxC)</option>
                    </select>
                </div>

                {{-- Receptor / Cliente --}}
                <div class="form-group">
                    <label class="form-label">
                        Cliente Receptor
                        @if($tipo_documento === '03' || $condicion_operacion === '2')
                            <span class="required">* (Obligatorio)</span>
                        @else
                            <span style="font-weight: normal; color: var(--neutral-400);">(Opcional)</span>
                        @endif
                    </label>
                    <select wire:model.live="cliente_id" class="form-select">
                        <option value="">Consumidor Final (Ventas al Paso)</option>
                        @foreach($this->clientes as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->nombre }} ({{ $c->nit ?: $c->nrc ?: 'Sin NIT' }})
                            </option>
                        @endforeach
                    </select>
                    @error('cliente_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror

                    {{-- Ficha Informativa del Cliente Seleccionado --}}
                    @if($clienteSeleccionado)
                        <div style="margin-top: 0.625rem; padding: 0.625rem 0.875rem; background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); font-size: 0.75rem;">
                            <div style="font-weight: 700; color: var(--neutral-800);">{{ $clienteSeleccionado->nombre }}</div>
                            <div style="color: var(--neutral-500); margin-top: 0.15rem;">
                                NIT: <strong>{{ $clienteSeleccionado->nit ?: 'N/D' }}</strong> | NRC: <strong>{{ $clienteSeleccionado->nrc ?: 'N/D' }}</strong>
                            </div>
                            <div style="color: var(--neutral-500); margin-top: 0.15rem;">
                                Dirección: {{ $clienteSeleccionado->direccion ?: 'Sin dirección registrada' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Resumen Financiero y Liquidación --}}
            <div class="card" style="padding: 1.5rem; background: linear-gradient(180deg, #ffffff 0%, var(--neutral-50) 100%);">
                <h3 style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--neutral-500); margin: 0 0 1rem;">
                    Resumen de Liquidación
                </h3>

                <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
                    
                    {{-- Subtotal Gravado --}}
                    <div style="display: flex; justify-content: space-between; align-items: center; color: var(--neutral-600);">
                        <span>Subtotal Gravado:</span>
                        <span style="font-weight: 600; color: var(--neutral-900);">${{ number_format($subtotal, 2) }}</span>
                    </div>

                    {{-- Desglose IVA para CCF --}}
                    @if($tipo_documento === '03')
                        <div style="display: flex; justify-content: space-between; align-items: center; color: var(--neutral-600);">
                            <span>IVA Débito Fiscal (13%):</span>
                            <span style="font-weight: 600; color: var(--warning-700);">${{ number_format($iva, 2) }}</span>
                        </div>
                    @else
                        <div style="display: flex; justify-content: space-between; align-items: center; color: var(--neutral-400); font-size: 0.75rem;">
                            <span>IVA (13% incluido):</span>
                            <span>${{ number_format($subtotal - ($subtotal / 1.13), 2) }}</span>
                        </div>
                    @endif

                    {{-- Separador decorativo --}}
                    <div style="border-top: 2px dashed var(--neutral-200); margin: 0.5rem 0;"></div>

                    {{-- TOTAL A PAGAR --}}
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <span style="font-size: 1.125rem; font-weight: 700; color: var(--neutral-900);">TOTAL A PAGAR</span>
                        <span style="font-size: 1.875rem; font-weight: 800; color: var(--primary-600); font-family: var(--font-heading); line-height: 1;">
                            ${{ number_format($total, 2) }}
                        </span>
                    </div>
                </div>

                {{-- Botón Principal Emitir y Firmar --}}
                <div style="margin-top: 1.5rem;">
                    @php $disabledBtn = count($detalles) === 0; @endphp
                    <button type="button" 
                            wire:click="facturar" 
                            class="btn btn-primary" 
                            style="width: 100%; height: 48px; padding: 0 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-md); box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; {{ $disabledBtn ? 'opacity: 0.5; cursor: not-allowed;' : '' }}" 
                            wire:loading.attr="disabled"
                            @if($disabledBtn) disabled @endif>
                        
                        <span wire:loading.remove wire:target="facturar" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; white-space: nowrap;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Emitir Factura DTE</span>
                        </span>

                        <span wire:loading.inline-flex wire:target="facturar" style="align-items: center; justify-content: center; gap: 0.5rem; white-space: nowrap;">
                            <svg class="animate-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-dasharray="32" stroke-linecap="round" fill="none" style="opacity: 0.3;"></circle>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"></path>
                            </svg>
                            <span>Transmitiendo DTE...</span>
                        </span>
                    </button>

                    <div style="margin-top: 0.75rem; text-align: center; font-size: 0.75rem; color: var(--neutral-400); display: flex; align-items: center; justify-content: center; gap: 0.35rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Firma Electrónica X.509 + Contingencia Automática</span>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
