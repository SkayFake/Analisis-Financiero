<div>
    @if(session('message'))
        <x-alerta-flash>{{ session('message') }}</x-alerta-flash>
    @endif
    @if($isOpen)
    <div class="modal-overlay" wire:keydown.escape="closeModal">
        <div class="modal movimiento-modal movimiento-modal--{{ $tipo }}" role="dialog" aria-labelledby="movimiento-titulo">
            <div class="modal-header movimiento-modal__cabecera">
                <span class="movimiento-modal__icono" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        @if($tipo === 'transferencia')
                            <path d="M3 8h17m-4-4 4 4-4 4M21 16H4m4-4-4 4 4 4"/>
                        @elseif($tipo === 'entrada')
                            <path d="M12 3v12m-4-4 4 4 4-4M4 15v5h16v-5"/>
                        @else
                            <path d="M12 15V3m-4 4 4-4 4 4M4 15v5h16v-5"/>
                        @endif
                    </svg>
                </span>
                <div>
                    <h3 class="modal-title" id="movimiento-titulo">
                        @if($tipo === 'entrada') Registrar entrada
                        @elseif($tipo === 'salida') Registrar salida
                        @else Transferencia entre bodegas @endif
                    </h3>
                    <p class="movimiento-modal__descripcion">
                        @if($tipo === 'entrada') Agrega existencias por compra o ajuste.
                        @elseif($tipo === 'salida') Descuenta existencias por consumo o merma.
                        @else Mueve existencias de una bodega a otra. @endif
                    </p>
                </div>
                <button type="button" class="modal-close" wire:click="closeModal" title="Cerrar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form class="movimiento-modal__formulario" wire:submit.prevent="guardar" x-data="{
                moneda: new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }),
                unidades: new Intl.NumberFormat('es-SV', { maximumFractionDigits: 4 }),
                etiqueta(campo, id, pendiente) {
                    return id ? Array.from(campo.options).find(opcion => opcion.value === String(id))?.label || pendiente : pendiente
                }
            }">
                <div class="modal-body movimiento-modal__cuerpo">
                    @include('components.errores-validacion')
                    @if(session()->has('error'))
                        <x-alerta-flash tipo="danger">{{ session('error') }}</x-alerta-flash>
                    @endif

                    <section class="movimiento-modal__seccion" aria-labelledby="movimiento-ubicacion">
                    <h4 id="movimiento-ubicacion">Producto y ubicación</h4>
                    <div class="form-group">
                        <label class="form-label" for="movimiento-producto">Producto <span class="required">*</span></label>
                        <select id="movimiento-producto" x-ref="producto" wire:model.live="producto_id" class="form-select" required>
                            <option value="">Seleccione un producto...</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->id }}" data-nombre="{{ $p->nombre }}">{{ $p->codigo }} — {{ $p->nombre }} (Stock Disp: {{ number_format($p->stock_total, 2) }})</option>
                            @endforeach
                        </select>
                        @error('producto_id') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="movimiento-modal__campos">
                        <div class="form-group">
                            <label class="form-label" for="movimiento-bodega">{{ $tipo === 'transferencia' ? 'Bodega de origen' : 'Bodega' }} <span class="required">*</span></label>
                            <select id="movimiento-bodega" x-ref="origen" wire:model="bodega_id" class="form-select" required>
                                <option value="">Seleccione bodega...</option>
                                @foreach($bodegas as $b)
                                    <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                                @endforeach
                            </select>
                            @error('bodega_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($tipo == 'transferencia')
                        <div class="form-group">
                            <label class="form-label" for="movimiento-destino">Bodega de destino <span class="required">*</span></label>
                            <select id="movimiento-destino" x-ref="destino" wire:model="bodega_destino_id" class="form-select" required>
                                <option value="">Seleccione bodega destino...</option>
                                @foreach($bodegas as $b)
                                    <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                                @endforeach
                            </select>
                            @error('bodega_destino_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        @endif

                    </div>
                    </section>

                    <section class="movimiento-modal__seccion" aria-labelledby="movimiento-cantidades">
                    <h4 id="movimiento-cantidades">{{ $tipo === 'entrada' ? 'Cantidad y costo' : 'Cantidad' }}</h4>
                    <div class="movimiento-modal__campos">
                        <div class="form-group">
                            <label class="form-label" for="movimiento-cantidad">Cantidad <span class="required">*</span></label>
                            <input id="movimiento-cantidad" type="number" wire:model="cantidad" step="0.0001" class="form-input" placeholder="1.00" required min="0.01" max="999999.9999">
                            @error('cantidad') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($tipo == 'entrada')
                        <div class="form-group">
                            <label class="form-label" for="movimiento-costo">Costo unitario ($) <span class="required">*</span></label>
                            <input id="movimiento-costo" type="number" wire:model="costo_unitario" step="0.0001" class="form-input" placeholder="0.0000" required min="0" max="99999999.9999">
                            @error('costo_unitario') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        @endif
                    </div>
                    </section>

                    <details class="movimiento-modal__detalles" wire:ignore.self>
                    <summary>Detalles opcionales</summary>
                    <div class="movimiento-modal__campos">
                        @if($tipo === 'entrada')
                        <div class="form-group">
                            <label class="form-label" for="movimiento-proveedor">Proveedor</label>
                            <select id="movimiento-proveedor" wire:model="proveedor_id" class="form-select">
                                <option value="">Sin proveedor / Ajuste interno</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <div class="form-group">
                            <label class="form-label" for="movimiento-referencia">Referencia del documento</label>
                            <input id="movimiento-referencia" type="text" wire:model="referencia" class="form-input" placeholder="Ej. REM-001" maxlength="255">
                        </div>
                        @endif
                        <div class="form-group">
                            <label class="form-label" for="movimiento-lote">Número de lote</label>
                            <input id="movimiento-lote" type="text" wire:model="lote" class="form-input" placeholder="Ej. L-2026-A1" maxlength="50">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="movimiento-vencimiento">Fecha de vencimiento</label>
                            <input id="movimiento-vencimiento" type="date" wire:model="fecha_vencimiento" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="movimiento-observaciones">Observaciones</label>
                        <textarea id="movimiento-observaciones" wire:model="observaciones" class="form-input" rows="2" maxlength="2000"></textarea>
                    </div>
                    </details>
                </div>

                <div class="modal-footer movimiento-modal__pie">
                    <div class="movimiento-modal__resumen">
                        <div>
                            <strong x-text="$wire.producto_id ? (Array.from($refs.producto.options).find(opcion => opcion.value === String($wire.producto_id))?.dataset.nombre || 'Producto pendiente') : 'Producto pendiente'">Producto pendiente</strong>
                            <small>Cantidad <span x-text="unidades.format(Number($wire.cantidad) || 0)"></span></small>
                        </div>
                        @if($tipo === 'entrada')
                            <div class="movimiento-modal__total">
                                <small>Costo estimado</small>
                                <strong x-text="moneda.format((Number($wire.cantidad) || 0) * (Number($wire.costo_unitario) || 0))"></strong>
                            </div>
                        @elseif($tipo === 'transferencia')
                            <small class="movimiento-modal__ruta">
                                <span x-text="etiqueta($refs.origen, $wire.bodega_id, 'Origen pendiente')"></span>
                                <span aria-hidden="true"> → </span>
                                <span x-text="etiqueta($refs.destino, $wire.bodega_destino_id, 'Destino pendiente')"></span>
                            </small>
                        @endif
                    </div>
                    <div class="movimiento-modal__acciones">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancelar</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardar"
                            class="btn movimiento-modal__confirmar">
                        <span wire:loading.remove wire:target="guardar">Confirmar {{ ucfirst($tipo) }}</span>
                        <span wire:loading wire:target="guardar">Procesando...</span>
                    </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

