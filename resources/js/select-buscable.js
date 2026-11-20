const selector = 'select:not([multiple]):not([size]), select:not([multiple])[size="1"]';

function normalizar(texto) {
    return texto.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('es').trim();
}

function iniciarSelectsBuscables() {
    const panel = document.createElement('div');
    if (!panel.showPopover) return;

    panel.className = 'select-buscable';
    panel.popover = 'manual';
    panel.innerHTML = `
        <input class="select-buscable__buscar" type="search" placeholder="Buscar opción…"
            role="combobox" aria-expanded="true" aria-controls="select-buscable-opciones"
            aria-autocomplete="list" autocomplete="off">
        <div class="select-buscable__opciones" id="select-buscable-opciones" role="listbox"></div>
        <div class="select-buscable__vacio" role="status" hidden>No se encontraron opciones</div>
    `;
    document.body.append(panel);

    const buscar = panel.querySelector('input');
    const lista = panel.querySelector('[role="listbox"]');
    const vacio = panel.querySelector('[role="status"]');
    let select;
    let opciones = [];
    let activa = -1;

    const observar = new MutationObserver(() => {
        if (select && (!select.isConnected || !select.getClientRects().length || select.disabled)) cerrar();
    });

    function cerrar(devolverFoco = false) {
        if (!select) return;

        const anterior = select;
        select = null;
        observar.disconnect();
        panel.hidePopover();
        if (devolverFoco && anterior.isConnected) anterior.focus({ preventScroll: true });
    }

    function posicionar() {
        if (!select) return;

        const rect = select.getBoundingClientRect();
        if (rect.bottom <= 0 || rect.top >= window.innerHeight) {
            cerrar();
            return;
        }
        const ancho = Math.min(Math.max(rect.width, 260), window.innerWidth - 16);
        const abajo = window.innerHeight - rect.bottom - 12;
        const arriba = rect.top - 12;
        const abrirArriba = abajo < 230 && arriba > abajo;
        panel.style.width = `${ancho}px`;
        panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - ancho - 8))}px`;
        panel.style.maxHeight = `${Math.min(320, abrirArriba ? arriba : abajo)}px`;
        panel.style.top = abrirArriba ? 'auto' : `${rect.bottom + 4}px`;
        panel.style.bottom = abrirArriba ? `${window.innerHeight - rect.top + 4}px` : 'auto';
    }

    function marcar(indice, desplazar = false) {
        lista.children[activa]?.classList.remove('is-active');
        activa = indice;
        const fila = lista.children[activa];
        if (!fila) {
            buscar.removeAttribute('aria-activedescendant');
            return;
        }
        fila.classList.add('is-active');
        buscar.setAttribute('aria-activedescendant', fila.id);
        if (desplazar) fila.scrollIntoView({ block: 'nearest' });
    }

    function mostrarOpciones() {
        const filtro = normalizar(buscar.value).split(/\s+/);
        opciones = Array.from(select.options).filter(opcion =>
            !opcion.hidden && !opcion.closest('optgroup[hidden]')
            && filtro.every(palabra => normalizar(opcion.label).includes(palabra)));
        lista.replaceChildren(...opciones.map((opcion, indice) => {
            const fila = document.createElement('div');
            fila.id = `select-buscable-opcion-${indice}`;
            fila.dataset.indice = indice;
            fila.setAttribute('role', 'option');
            fila.setAttribute('aria-selected', String(opcion.selected));
            fila.setAttribute('aria-disabled', String(opcion.matches(':disabled')));
            fila.textContent = opcion.label;
            return fila;
        }));
        activa = -1;
        lista.scrollTop = 0;
        vacio.hidden = opciones.length > 0;
        const seleccionada = opciones.findIndex(opcion => opcion.selected && !opcion.matches(':disabled'));
        marcar(seleccionada >= 0 ? seleccionada : opciones.findIndex(opcion => !opcion.matches(':disabled')), true);
    }

    function abrir(campo) {
        if (select === campo) return;
        cerrar();
        select = campo;
        const etiqueta = campo.labels?.[0]?.cloneNode(true)
            ?? campo.closest('.form-group')?.querySelector('label')?.cloneNode(true);
        etiqueta?.querySelector('select')?.remove();
        const nombre = campo.getAttribute('aria-label') || etiqueta?.textContent.trim() || 'opción';
        buscar.setAttribute('aria-label', `Buscar en ${nombre}`);
        lista.setAttribute('aria-label', nombre);
        buscar.value = '';
        posicionar();
        if (!select) return;
        panel.showPopover();
        mostrarOpciones();
        buscar.focus({ preventScroll: true });
        observar.observe(document.body, { childList: true, subtree: true });
        observar.observe(campo, { attributes: true, attributeFilter: ['disabled'] });
    }

    function elegir(indice) {
        const opcion = opciones[indice];
        if (!opcion || !select.contains(opcion) || opcion.matches(':disabled')) return;

        const campo = select;
        const cambiado = campo.selectedIndex !== opcion.index;
        campo.selectedIndex = opcion.index;
        cerrar(true);
        if (cambiado) {
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    document.addEventListener('pointerdown', evento => {
        if (evento.button !== 0) return;
        const campo = evento.target.closest(selector);
        if (campo && !campo.matches(':disabled')) {
            evento.preventDefault();
            abrir(campo);
        } else if (!panel.contains(evento.target)) {
            cerrar();
        }
    });
    document.addEventListener('click', evento => {
        const campo = evento.target.closest(selector);
        if (!campo || campo.matches(':disabled')) return;
        evento.preventDefault();
        abrir(campo);
    });
    document.addEventListener('keydown', evento => {
        if (!evento.target.matches(selector) || evento.target.matches(':disabled')) return;
        if (!['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(evento.key)) return;
        evento.preventDefault();
        abrir(evento.target);
    });

    buscar.addEventListener('input', mostrarOpciones);
    buscar.addEventListener('keydown', evento => {
        switch (evento.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                evento.preventDefault();
                const paso = evento.key === 'ArrowDown' ? 1 : -1;
                for (let indice = activa + paso; indice >= 0 && indice < opciones.length; indice += paso) {
                    if (opciones[indice].matches(':disabled')) continue;
                    marcar(indice, true);
                    break;
                }
                break;
            }
            case 'Enter':
                evento.preventDefault();
                elegir(activa);
                break;
            case 'Escape':
                evento.preventDefault();
                evento.stopPropagation();
                cerrar(true);
                break;
            case 'Tab':
                cerrar(true);
                break;
        }
    });
    lista.addEventListener('pointerdown', evento => evento.preventDefault());
    lista.addEventListener('click', evento => {
        const fila = evento.target.closest('[data-indice]');
        if (fila) elegir(Number(fila.dataset.indice));
    });
    window.addEventListener('resize', posicionar);
    window.addEventListener('scroll', evento => {
        if (!panel.contains(evento.target)) posicionar();
    }, true);
}

iniciarSelectsBuscables();
