@props(['tipo' => 'success'])

<div {{ $attributes->class(['alert', 'alert-'.$tipo, 'alerta-flash']) }} role="{{ $tipo === 'danger' ? 'alert' : 'status' }}"
     x-data="{ visible: true }" x-show="visible">
    <div class="alerta-contenido">{{ $slot }}</div>
    <button type="button" class="alerta-cerrar" aria-label="Cerrar aviso" @click="visible = false">
        <span aria-hidden="true">×</span>
    </button>
</div>
