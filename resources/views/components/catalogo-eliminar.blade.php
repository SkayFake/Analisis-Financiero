@props(['item'])

<button type="button" class="btn btn-sm btn-ghost btn-delete" data-nombre="{{ $item->nombre }}"
        @click="registroPendiente = {{ $item->id }}; nombrePendiente = $el.dataset.nombre; $refs.confirmacion.showModal()">
    Eliminar
</button>
