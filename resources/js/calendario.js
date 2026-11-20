const campos = 'input[type="date"], input[type="month"]';
const formatoMes = new Intl.DateTimeFormat('es', { month: 'long' });
const formatoFecha = new Intl.DateTimeFormat('es', { dateStyle: 'full' });
const completar = numero => String(numero).padStart(2, '0');
const periodo = fecha => `${String(fecha.getFullYear()).padStart(4, '0')}-${completar(fecha.getMonth() + 1)}`;
const valorFecha = fecha => `${periodo(fecha)}-${completar(fecha.getDate())}`;

function iniciarCalendario() {
    const panel = document.createElement('div');
    if (!panel.showPopover) return;

    panel.className = 'calendario';
    panel.popover = 'manual';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-labelledby', 'calendario-titulo');
    panel.innerHTML = `
        <header class="calendario__cabecera">
            <button type="button" data-paso="-1" aria-label="Anterior">‹</button>
            <strong id="calendario-titulo" aria-live="polite"></strong>
            <button type="button" data-paso="1" aria-label="Siguiente">›</button>
        </header>
        <div class="calendario__semana" aria-hidden="true"></div>
        <div class="calendario__opciones"></div>
        <footer class="calendario__pie"><button type="button" data-limpiar>Limpiar</button></footer>
    `;
    document.body.append(panel);
    document.documentElement.classList.add('calendario-disponible');

    const titulo = panel.querySelector('strong');
    const semana = panel.querySelector('.calendario__semana');
    const opciones = panel.querySelector('.calendario__opciones');
    const limpiar = panel.querySelector('[data-limpiar]');
    const hoy = new Date();
    let campo;
    let vista;

    semana.replaceChildren(...['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'].map(dia => {
        const celda = document.createElement('span');
        celda.textContent = dia;
        return celda;
    }));

    function cerrar(devolverFoco = false) {
        if (!campo) return;
        const anterior = campo;
        campo = null;
        panel.hidePopover();
        if (devolverFoco && anterior.isConnected) anterior.focus({ preventScroll: true });
    }

    function posicionar() {
        if (!campo) return;
        const rect = campo.getBoundingClientRect();
        if (!campo.isConnected || !rect.width || rect.bottom <= 0 || rect.top >= window.innerHeight) {
            cerrar();
            return;
        }
        const ancho = panel.offsetWidth;
        const alto = panel.offsetHeight;
        const top = rect.bottom + alto + 12 > window.innerHeight && rect.top > alto + 8
            ? rect.top - alto - 6 : rect.bottom + 6;
        panel.style.top = `${Math.max(8, Math.min(top, window.innerHeight - alto - 8))}px`;
        panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - ancho - 8))}px`;
    }

    function mover(paso) {
        const nueva = new Date(vista);
        if (campo.type === 'month') nueva.setFullYear(vista.getFullYear() + paso);
        else nueva.setMonth(vista.getMonth() + paso);
        return nueva;
    }

    function renderizar() {
        const porMes = campo.type === 'month';
        const anio = vista.getFullYear();
        const mes = vista.getMonth();
        const minimo = campo.min.slice(0, porMes ? 4 : 7);
        const maximo = campo.max.slice(0, porMes ? 4 : 7);
        const clave = fecha => porMes ? periodo(fecha).slice(0, 4) : periodo(fecha);
        titulo.textContent = porMes ? anio : `${formatoMes.format(vista)} de ${anio}`;
        panel.querySelector('[data-paso="-1"]').disabled = anio === 1 && (porMes || mes === 0)
            || Boolean(minimo && clave(mover(-1)) < minimo);
        panel.querySelector('[data-paso="1"]').disabled = anio === 9999 && (porMes || mes === 11)
            || Boolean(maximo && clave(mover(1)) > maximo);
        semana.hidden = porMes;
        opciones.classList.toggle('calendario__opciones--meses', porMes);
        opciones.replaceChildren();
        const diasDelMes = new Date(vista);
        diasDelMes.setMonth(mes + 1, 0);
        if (!porMes) {
            for (let i = 0; i < (vista.getDay() + 6) % 7; i++) {
                opciones.append(document.createElement('span'));
            }
        }

        const validar = campo.cloneNode();
        for (let i = 0; i < (porMes ? 12 : diasDelMes.getDate()); i++) {
            const fecha = new Date(vista);
            if (porMes) fecha.setMonth(i);
            else fecha.setDate(i + 1);
            const valor = porMes ? periodo(fecha) : valorFecha(fecha);
            validar.value = valor;
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.dataset.valor = valor;
            boton.textContent = porMes ? formatoMes.format(fecha).slice(0, 3) : i + 1;
            boton.setAttribute('aria-label', porMes ? `${formatoMes.format(fecha)} de ${anio}` : formatoFecha.format(fecha));
            boton.setAttribute('aria-pressed', String(campo.value === valor));
            boton.disabled = !validar.validity.valid;
            if (valor === (porMes ? periodo(hoy) : valorFecha(hoy))) boton.setAttribute('aria-current', 'date');
            opciones.append(boton);
        }
        limpiar.hidden = campo.required;
        posicionar();
    }

    function abrir(input) {
        if (campo === input) return;
        cerrar();
        campo = input;
        const hoyISO = valorFecha(hoy);
        const referencia = (campo.value || hoyISO).slice(0, campo.type === 'month' ? 7 : 10);
        const limite = referencia < campo.min ? campo.min : (campo.max && referencia > campo.max ? campo.max : referencia);
        const [anio, mes] = limite.split('-').map(Number);
        vista = new Date(hoy);
        vista.setFullYear(anio, mes - 1, 1);
        panel.showPopover();
        renderizar();
        (opciones.querySelector('[aria-pressed="true"]:enabled')
            ?? opciones.querySelector('[aria-current]:enabled')
            ?? opciones.querySelector('button:enabled')
            ?? panel.querySelector('button:enabled'))?.focus({ preventScroll: true });
    }

    function elegir(valor) {
        const input = campo;
        const cambio = input.value !== valor;
        input.value = valor;
        cerrar(true);
        if (cambio) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function esIcono(evento, input) {
        return input.getBoundingClientRect().right - evento.clientX <= 42;
    }

    document.addEventListener('pointerdown', evento => {
        if (evento.button !== 0) return;
        const input = evento.target.closest(campos);
        if (input && !input.matches(':disabled, [readonly]') && esIcono(evento, input)) {
            evento.preventDefault();
            abrir(input);
        } else if (!panel.contains(evento.target)) cerrar();
    });
    document.addEventListener('click', evento => {
        const input = evento.target.closest(campos);
        if (input && !input.matches(':disabled, [readonly]') && esIcono(evento, input)) {
            evento.preventDefault();
            abrir(input);
        }
    });
    document.addEventListener('keydown', evento => {
        const input = evento.target.closest(campos);
        if (!input || input.matches(':disabled, [readonly]') || !evento.altKey || evento.key !== 'ArrowDown') return;
        evento.preventDefault();
        abrir(input);
    });

    panel.addEventListener('click', evento => {
        const boton = evento.target.closest('button');
        if (!boton || !campo) return;
        if (boton.hasAttribute('data-paso')) {
            vista = mover(Number(boton.dataset.paso));
            renderizar();
            if (boton.disabled) opciones.querySelector('button:enabled')?.focus({ preventScroll: true });
        } else elegir(boton.dataset.valor ?? '');
    });
    panel.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') {
            evento.preventDefault();
            evento.stopPropagation();
            cerrar(true);
        }
        const columnas = campo?.type === 'month' ? 3 : 7;
        const paso = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -columnas, ArrowDown: columnas }[evento.key];
        if (!paso || !evento.target.matches('[data-valor]')) return;
        evento.preventDefault();
        const celdas = Array.from(opciones.children);
        const siguiente = celdas[celdas.indexOf(evento.target) + paso];
        if (siguiente?.matches('button:enabled')) siguiente.focus({ preventScroll: true });
    });
    document.addEventListener('focusin', evento => {
        if (campo && !panel.contains(evento.target) && evento.target !== campo) cerrar();
    });
    window.addEventListener('resize', posicionar);
    window.addEventListener('scroll', posicionar, true);
}

iniciarCalendario();
