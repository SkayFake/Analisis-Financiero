// Máscaras y validaciones interactivas para El Salvador
document.addEventListener('DOMContentLoaded', () => {
    initMascaras();
});

// También escuchar eventos de Livewire para elementos agregados dinámicamente
document.addEventListener('livewire:navigated', () => {
    initMascaras();
});
document.addEventListener('livewire:initialized', () => {
    initMascaras();
});

function initMascaras() {
    // Delegación global para inputs interactivos
    document.body.addEventListener('input', (e) => {
        const target = e.target;
        if (!target || target.tagName !== 'INPUT') return;

        const name = (target.getAttribute('name') || '').toLowerCase();
        const wireModel = (target.getAttribute('wire:model') || target.getAttribute('wire:model.live') || '').toLowerCase();
        const type = (target.getAttribute('type') || '').toLowerCase();
        const inputmode = (target.getAttribute('inputmode') || '').toLowerCase();

        // 1. Números puros / moneda (evitar letras)
        if (type === 'number') {
            return; // type="number" ya maneja caracteres numéricos en el navegador
        }

        // 2. DUI (Formato: 00000000-0)
        if (name.includes('dui') || wireModel.includes('dui') || target.dataset.mask === 'dui') {
            aplicarMascaraDui(target);
            return;
        }

        // 3. Teléfono / Celular (Formato: 0000-0000 o +503 0000-0000)
        if (name.includes('telefono') || name.includes('celular') || wireModel.includes('telefono') || wireModel.includes('celular') || target.dataset.mask === 'telefono') {
            aplicarMascaraTelefono(target);
            return;
        }

        // 4. NIT (Formato tradicional: 0000-000000-000-0 o DUI homologado si tiene 9 dígitos)
        if (name === 'nit' || wireModel === 'nit' || target.dataset.mask === 'nit') {
            aplicarMascaraNit(target);
            return;
        }

        // 5. NRC (Números opcionalmente con guion antes del último dígito)
        if (name === 'nrc' || wireModel === 'nrc' || target.dataset.mask === 'nrc') {
            aplicarMascaraNrc(target);
            return;
        }
    });

    // Bloquear teclas no numéricas en campos que son puramente números / documentos
    document.body.addEventListener('keydown', (e) => {
        const target = e.target;
        if (!target || target.tagName !== 'INPUT') return;

        const name = (target.getAttribute('name') || '').toLowerCase();
        const wireModel = (target.getAttribute('wire:model') || target.getAttribute('wire:model.live') || '').toLowerCase();
        const type = (target.getAttribute('type') || '').toLowerCase();

        const esDocumentoONumero = type === 'number' || 
            name.includes('dui') || wireModel.includes('dui') ||
            name.includes('telefono') || name.includes('celular') || wireModel.includes('telefono') || wireModel.includes('celular') ||
            name === 'nit' || wireModel === 'nit' ||
            name === 'nrc' || wireModel === 'nrc';

        if (esDocumentoONumero) {
            // Permitir teclas especiales de control: Backspace, Tab, Enter, Escape, Delete, Flechas, etc.
            if ([8, 9, 13, 27, 46, 37, 38, 39, 40].includes(e.keyCode)) return;
            // Permitir Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            if (e.ctrlKey || e.metaKey) return;

            // Para tipo number, permitir punto decimal si no tiene uno
            if (type === 'number') {
                if (e.key === '.' || e.key === ',') return;
            }

            // Para teléfonos permitir opcionalmente '+'
            if (name.includes('telefono') || name.includes('celular') || wireModel.includes('telefono') || wireModel.includes('celular')) {
                if (e.key === '+' && target.selectionStart === 0 && !target.value.includes('+')) return;
            }

            // Bloquear cualquier tecla que no sea dígito
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        }
    });
}

function aplicarMascaraDui(input) {
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw.length > 9) raw = raw.substring(0, 9);

    let formatted = raw;
    if (raw.length > 8) {
        formatted = raw.substring(0, 8) + '-' + raw.substring(8);
    }
    if (input.value !== formatted) {
        input.value = formatted;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function aplicarMascaraTelefono(input) {
    let val = input.value;
    let tienePlus = val.startsWith('+503');
    let raw = val.replace(/[^0-9]/g, '');

    if (tienePlus && raw.startsWith('503')) {
        raw = raw.substring(3);
    }
    if (raw.length > 8) raw = raw.substring(0, 8);

    let formatted = '';
    if (tienePlus) {
        formatted = '+503 ';
    }
    if (raw.length > 4) {
        formatted += raw.substring(0, 4) + '-' + raw.substring(4);
    } else {
        formatted += raw;
    }

    if (input.value !== formatted) {
        input.value = formatted;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function aplicarMascaraNit(input) {
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw.length > 14) raw = raw.substring(0, 14);

    let formatted = raw;
    // Si tiene hasta 9 dígitos y el usuario está escribiendo un DUI homologado
    if (raw.length <= 9) {
        if (raw.length > 8) {
            formatted = raw.substring(0, 8) + '-' + raw.substring(8);
        }
    } else {
        // Formato NIT tradicional de 14 dígitos: 0000-000000-000-0
        if (raw.length > 13) {
            formatted = raw.substring(0, 4) + '-' + raw.substring(4, 10) + '-' + raw.substring(10, 13) + '-' + raw.substring(13);
        } else if (raw.length > 10) {
            formatted = raw.substring(0, 4) + '-' + raw.substring(4, 10) + '-' + raw.substring(10);
        } else if (raw.length > 4) {
            formatted = raw.substring(0, 4) + '-' + raw.substring(4);
        }
    }

    if (input.value !== formatted) {
        input.value = formatted;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function aplicarMascaraNrc(input) {
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw.length > 8) raw = raw.substring(0, 8);

    let formatted = raw;
    if (raw.length > 1) {
        formatted = raw.substring(0, raw.length - 1) + '-' + raw.substring(raw.length - 1);
    }

    if (input.value !== formatted) {
        input.value = formatted;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

