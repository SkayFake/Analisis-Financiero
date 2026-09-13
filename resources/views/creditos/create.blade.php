<x-app-layout>
    <x-slot name="title">Nuevo Crédito</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Otorgar Nuevo Crédito</h1>
            <p class="page-subtitle">Genera un nuevo préstamo con cálculo de amortización automática.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.index') }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                <span>Volver a Créditos</span>
            </a>
        </div>
    </div>

    <div class="card" style="max-width: 860px;" x-data="{ cuotas: {{ old('numero_cuotas', 12) }} }">
        <form action="{{ route('creditos.store') }}" method="POST">
            @csrf
            <div class="card-body" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Condiciones del Crédito
                    </h3>
                    <div class="grid-2">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Cliente Titular <span class="required">*</span></label>
                            <select name="cliente_id" class="form-select @error('cliente_id') error @enderror" required>
                                <option value="">Seleccione un cliente...</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id }}" {{ old('cliente_id', request('cliente_id')) == $cliente->id ? 'selected' : '' }}>
                                        [{{ $cliente->codigo }}] {{ $cliente->nombre }} ({{ strtoupper($cliente->tipo) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Producto de Crédito <span class="required">*</span></label>
                            <select name="producto_credito_id" class="form-select @error('producto_credito_id') error @enderror" required>
                                <option value="">Seleccione un producto...</option>
                                @foreach($productos as $producto)
                                    <option value="{{ $producto->id }}" {{ old('producto_credito_id') == $producto->id ? 'selected' : '' }}>
                                        {{ $producto->nombre }} ({{ number_format($producto->tasa_interes, 2) }}% interés anual • {{ number_format($producto->comision, 2) }}% comisión)
                                    </option>
                                @endforeach
                            </select>
                            @error('producto_credito_id')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Monto a Financiar ($) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="monto" class="form-input @error('monto') error @enderror" value="{{ old('monto') }}" required min="100" placeholder="0.00">
                            @error('monto')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número de Cuotas (Meses) <span class="required">*</span></label>
                            <input type="number" name="numero_cuotas" x-model.number="cuotas" class="form-input @error('numero_cuotas') error @enderror" required min="1" max="120">
                            <span class="form-hint">Equivale a <strong x-text="cuotas * 30"></strong> días de plazo total.</span>
                            @error('numero_cuotas')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha de Desembolso</label>
                            <input type="date" name="fecha_desembolso" class="form-input" value="{{ old('fecha_desembolso', date('Y-m-d')) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tipo de Venta / Operación</label>
                            <select name="tipo_venta" class="form-select">
                                <option value="credito" selected>Crédito Cuota Fija (Sistema Francés)</option>
                                <option value="contado">Contado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Asignación y Notas
                    </h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Gestor / Asesor</label>
                            <select name="vendedor_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($vendedores as $v)
                                    <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cartera</label>
                            <select name="cartera_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}" {{ old('cartera_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-input" rows="2" placeholder="Detalles de garantía, destino del crédito, etc.">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="{{ route('creditos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Generar y Desembolsar Crédito</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
