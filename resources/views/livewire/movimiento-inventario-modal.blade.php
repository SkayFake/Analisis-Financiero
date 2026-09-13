<div>
    @if($isOpen)
    <div class="modal-overlay" wire:keydown.escape="closeModal">
        <div class="modal" style="max-width: 620px;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">
                        @if($tipo == 'entrada') Registrar Entrada a Inventario (Compra / Ajuste +)
                        @elseif($tipo == 'salida') Registrar Salida de Inventario (Merma / Consumo)
                        @else Transferencia entre Bodegas @endif
                    </h3>
                    <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                        Afectación directa de kardex e inventarios en tiempo real.
                    </p>
                </div>
                <button type="button" class="modal-close" wire:click="closeModal" title="Cerrar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="guardar">
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    @if(session()->has('error'))
                        <div class="alert alert-danger" style="margin-bottom: 0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    {{-- Bodegas --}}
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Bodega {{ $tipo == 'transferencia' ? 'Origen' : 'Almacén' }} <span class="required">*</span></label>
                            <select wire:model="bodega_id" class="form-select" required>
                                <option value="">Seleccione bodega...</option>
                                @foreach($bodegas as $b)
                                    <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                                @endforeach
                            </select>
                            @error('bodega_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($tipo == 'transferencia')
                        <div class="form-group">
                            <label class="form-label">Bodega Destino <span class="required">*</span></label>
                            <select wire:model="bodega_destino_id" class="form-select" required>
                                <option value="">Seleccione bodega destino...</option>
                                @foreach($bodegas as $b)
                                    <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                                @endforeach
                            </select>
                            @error('bodega_destino_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        @if($tipo == 'entrada')
                        <div class="form-group">
                            <label class="form-label">Proveedor (Opcional)</label>
                            <select wire:model="proveedor_id" class="form-select">
                                <option value="">Sin proveedor / Ajuste interno</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>

                    {{-- Selección de Producto --}}
                    <div class="form-group">
                        <label class="form-label">Producto a Registrar <span class="required">*</span></label>
                        <select wire:model.live="producto_id" class="form-select" required>
                            <option value="">Seleccione un producto...</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }} (Stock Disp: {{ number_format($p->stock_total, 2) }})</option>
                            @endforeach
                        </select>
                        @error('producto_id') <span class="form-error">{{ $message }}</span> @enderror
                    </div>

                    {{-- Cantidad y Costo --}}
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Cantidad <span class="required">*</span></label>
                            <input type="number" wire:model="cantidad" step="0.01" class="form-input" placeholder="1.00" required min="0.01">
                            @error('cantidad') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($tipo == 'entrada')
                        <div class="form-group">
                            <label class="form-label">Costo Unitario Compra ($) <span class="required">*</span></label>
                            <input type="number" wire:model="costo_unitario" step="0.0001" class="form-input" placeholder="0.0000" required min="0">
                            @error('costo_unitario') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        @else
                        <div class="form-group">
                            <label class="form-label">Referencia Documento</label>
                            <input type="text" wire:model="referencia" class="form-input" placeholder="Ej: REM-001, Vale #45">
                        </div>
                        @endif
                    </div>

                    {{-- Lote y Vencimiento --}}
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Número de Lote (Opcional)</label>
                            <input type="text" wire:model="lote" class="form-input" placeholder="Ej. L-2026-A1">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha de Vencimiento (Opcional)</label>
                            <input type="date" wire:model="fecha_vencimiento" class="form-input">
                        </div>
                    </div>

                    {{-- Observaciones --}}
                    <div class="form-group">
                        <label class="form-label">Observaciones / Justificación</label>
                        <textarea wire:model="observaciones" class="form-input" rows="2" placeholder="Detalle adicional del movimiento..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancelar</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="btn btn-{{ $tipo == 'entrada' ? 'success' : ($tipo == 'salida' ? 'danger' : 'primary') }}">
                        <span wire:loading.remove wire:target="guardar">Confirmar {{ ucfirst($tipo) }}</span>
                        <span wire:loading wire:target="guardar">Procesando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

