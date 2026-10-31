/* Canasta Básica CR · dashboard (jQuery + Chart.js) */
$(function () {
    'use strict';

    // ─── Formato ────────────────────────────────────────────────────────
    const MESES  = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const nfs    = {};
    const nf     = (d) => (nfs[d] ??= new Intl.NumberFormat('es-CR', { minimumFractionDigits: d, maximumFractionDigits: d }));
    const mesTxt = (ym) => ym ? MESES[+ym.slice(5, 7) - 1] + ' ' + ym.slice(0, 4) : '—';
    const mesCor = (ym) => MESES[+ym.slice(5, 7) - 1] + ' ' + ym.slice(2, 4);
    const pct    = (v, d = 2) => v == null ? '—' : (v > 0 ? '+' : '') + nf(d).format(v) + ' %';
    const pp     = (v) => v == null ? '—' : (v > 0 ? '+' : '') + nf(2).format(v) + ' p.p.';
    const crc    = (v) => v == null ? '—' : '₡' + nf(0).format(v);
    const usd    = (v) => v == null ? '—' : 'US$' + nf(2).format(v);
    const esc    = (s) => $('<div>').text(s ?? '').html();
    const clsVar = (v) => v == null ? '' : (v > 0 ? 'var-pos' : (v < 0 ? 'var-neg' : ''));

    /** Delta con flecha. invertido = true cuando subir es bueno (ej. salario real). */
    function delta(v, txt, invertido = false) {
        if (v == null) return '<span class="delta flat">—</span>';
        const sube = v > 0, baja = v < 0;
        const cls  = !sube && !baja ? 'flat' : ((sube !== invertido) ? 'up' : 'down');
        const ico  = sube ? 'bi-arrow-up-right' : (baja ? 'bi-arrow-down-right' : 'bi-dash');
        return `<span class="delta ${cls}"><i class="bi ${ico}"></i>${txt ?? pct(v)}</span>`;
    }

    // ─── Tokens de color (se releen al cambiar de tema) ─────────────────
    let T = tokens();
    function tokens() {
        const s = getComputedStyle(document.documentElement);
        const g = (n) => s.getPropertyValue(n).trim();
        return {
            text: g('--text'), text2: g('--text-2'), muted: g('--muted'), grid: g('--grid'), axis: g('--axis'),
            surface: g('--surface'), accent: g('--accent'), band: g('--meta-band'),
            s: [g('--s1'), g('--s2'), g('--s3'), g('--s4')], neutral: g('--s-neutral'),
            neg: g('--div-neg'), mid: g('--div-mid'), pos: g('--div-pos'),
        };
    }

    const hex2rgb = (h) => { h = h.replace('#', ''); return [0, 2, 4].map(i => parseInt(h.substr(i, 2), 16)); };
    const rgb2hex = (c) => '#' + c.map(x => Math.round(x).toString(16).padStart(2, '0')).join('');
    const mezcla  = (a, b, t) => { const x = hex2rgb(a), y = hex2rgb(b); return rgb2hex(x.map((v, i) => v + (y[i] - v) * t)); };
    const luz     = (h) => { const [r, g, b] = hex2rgb(h).map(v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }); return .2126 * r + .7152 * g + .0722 * b; };

    /** Divergente: azul (baja) ↔ neutro ↔ rojo (sube). */
    const colorDiv = (v, max) => {
        if (v == null) return T.mid;
        const t = Math.max(-1, Math.min(1, v / max));
        return mezcla(T.mid, t >= 0 ? T.pos : T.neg, Math.abs(t));
    };
    const tintaSobre = (bg) => luz(bg) > .35 ? '#0b0b0b' : '#ffffff';

    // ─── Chart.js: valores por defecto y plugins ────────────────────────
    function defaults() {
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.font.size   = 11.5;
        Chart.defaults.color       = T.muted;
    }
    defaults();

    // Crosshair vertical en líneas
    Chart.register({
        id: 'crosshair',
        afterDatasetsDraw(chart) {
            const act = chart.tooltip?.getActiveElements?.() ?? [];
            if (chart.config.type !== 'line' || !act.length) return;
            const { ctx, chartArea: a } = chart, x = act[0].element.x;
            ctx.save();
            ctx.strokeStyle = T.axis; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(x, a.top); ctx.lineTo(x, a.bottom); ctx.stroke();
            ctx.restore();
        },
    });

    // Banda de la meta de inflación
    Chart.register({
        id: 'metaBand',
        beforeDatasetsDraw(chart, _, o) {
            if (!o || o.min == null) return;
            const { ctx, chartArea: a, scales: { y } } = chart;
            const top = y.getPixelForValue(o.max), bot = y.getPixelForValue(o.min);
            ctx.save();
            ctx.fillStyle = T.band;
            ctx.fillRect(a.left, Math.max(top, a.top), a.right - a.left, Math.min(bot, a.bottom) - Math.max(top, a.top));
            ctx.restore();
        },
    });

    // Línea de referencia (IPC general en barras, 100 en índices)
    Chart.register({
        id: 'refLine',
        afterDatasetsDraw(chart, _, o) {
            if (!o || o.value == null) return;
            const { ctx, chartArea: a } = chart, sc = chart.scales[o.axis];
            const p = sc.getPixelForValue(o.value);
            ctx.save();
            ctx.strokeStyle = T.text2; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
            ctx.beginPath();
            if (o.axis === 'x') { ctx.moveTo(p, a.top); ctx.lineTo(p, a.bottom); } else { ctx.moveTo(a.left, p); ctx.lineTo(a.right, p); }
            ctx.stroke();
            if (o.label) {
                ctx.setLineDash([]); ctx.fillStyle = T.text2; ctx.font = "600 11px Inter, system-ui";
                ctx.textAlign = o.axis === 'x' ? 'center' : 'left';
                o.axis === 'x' ? ctx.fillText(o.label, p, a.top - 6) : ctx.fillText(o.label, a.left + 4, p - 5);
            }
            ctx.restore();
        },
    });

    // Etiqueta directa al final de cada línea (alivio de contraste + identidad sin color)
    Chart.register({
        id: 'endLabels',
        afterDatasetsDraw(chart, _, o) {
            if (!o || !o.enabled || chart.config.type !== 'line') return;
            const { ctx, chartArea: a } = chart;
            const etiquetas = [];
            chart.data.datasets.forEach((ds, i) => {
                const meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                for (let k = ds.data.length - 1; k >= 0; k--) {
                    if (ds.data[k] != null) {
                        const pt = meta.data[k];
                        etiquetas.push({ y: pt.y, x: pt.x, color: ds.borderColor, txt: o.fmt(ds.data[k]) });
                        break;
                    }
                }
            });
            etiquetas.sort((p, q) => p.y - q.y);
            for (let i = 1; i < etiquetas.length; i++) {
                if (etiquetas[i].y - etiquetas[i - 1].y < 14) etiquetas[i].y = etiquetas[i - 1].y + 14;
            }
            ctx.save();
            ctx.font = "600 11px Inter, system-ui"; ctx.textBaseline = 'middle';
            etiquetas.forEach(e => {
                const y = Math.min(Math.max(e.y, a.top + 6), a.bottom - 6);
                ctx.fillStyle = e.color; ctx.beginPath(); ctx.arc(a.right + 9, y, 3.5, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = T.text2; ctx.fillText(e.txt, a.right + 16, y);
            });
            ctx.restore();
        },
    });

    const charts = {};
    function grafico(id, config) {
        if (charts[id]) charts[id].destroy();
        const el = document.getElementById(id);
        $(el).closest('.chart-box, .chart-fill').find('.chart-error').remove();
        charts[id] = new Chart(el, config);
        return charts[id];
    }

    function tooltip(fmt) {
        return {
            backgroundColor: T.surface, titleColor: T.text, bodyColor: T.text2, borderColor: T.axis, borderWidth: 1,
            padding: 10, cornerRadius: 10, boxPadding: 4, usePointStyle: true,
            titleFont: { weight: '700' },
            callbacks: {
                title: (items) => items.length ? (items[0].chart.$titulos?.[items[0].dataIndex] ?? items[0].label) : '',
                label: (c) => ' ' + c.dataset.label + ': ' + (c.parsed.y == null && c.parsed.x == null ? '—' : fmt(c.chart.options.indexAxis === 'y' ? c.parsed.x : c.parsed.y)),
            },
        };
    }

    function opcionesLinea(fmtEje, fmtValor, extra = {}) {
        return {
            responsive: true, maintainAspectRatio: false, animation: { duration: 300 },
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { right: 78, top: 8 } },
            plugins: {
                legend: { display: false },
                tooltip: tooltip(fmtValor),
                endLabels: { enabled: true, fmt: fmtValor },
                ...extra,
            },
            scales: {
                x: { grid: { display: false }, border: { color: T.axis }, ticks: { maxRotation: 0, autoSkipPadding: 18 } },
                y: { grid: { color: T.grid }, border: { display: false }, ticks: { callback: fmtEje, maxTicksLimit: 7 } },
            },
        };
    }

    function linea(label, data, color, extra = {}) {
        return {
            label, data, borderColor: color, backgroundColor: color, borderWidth: 2, pointRadius: 0,
            pointHoverRadius: 5, pointHoverBorderWidth: 2, pointHoverBorderColor: T.surface, tension: .25, spanGaps: true, ...extra,
        };
    }

    /** Barras horizontales ordenadas; el color solo indica el signo (el largo ya muestra la magnitud). */
    function barrasDivergentes(id, etiquetas, valores, fmt, ref) {
        const color = (v) => v >= 0 ? T.pos : T.neg;
        const c = grafico(id, {
            type: 'bar',
            data: {
                labels: etiquetas,
                datasets: [{
                    label: 'Variación', data: valores,
                    backgroundColor: valores.map(color),
                    hoverBackgroundColor: valores.map(v => mezcla(color(v), T.text, .2)),
                    borderRadius: 4, borderSkipped: false, categoryPercentage: .78, barPercentage: .9,
                }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: { duration: 300 },
                layout: { padding: { top: ref ? 18 : 4, right: 8 } },
                plugins: { legend: { display: false }, tooltip: tooltip(fmt), refLine: ref ?? {} },
                scales: {
                    x: {
                        grid: { color: (ctx) => ctx.tick.value === 0 ? T.axis : T.grid, lineWidth: (ctx) => ctx.tick.value === 0 ? 1.5 : 1 },
                        border: { display: false }, ticks: { callback: (v) => nf(Number.isInteger(v) ? 0 : 1).format(v), maxTicksLimit: 7 },
                    },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: T.text2, font: { weight: '600' } } },
                },
            },
        });
        return c;
    }

    function leyenda(sel, items) {
        $(sel).html(items.map(i => i.band
            ? `<span><i class="band"></i>${esc(i.label)}</span>`
            : `<span><i class="sw ${i.dash ? 'dash' : ''}" style="background:${i.color};color:${i.color}"></i>${esc(i.label)}</span>`).join(''));
    }

    // ─── Tabla de datos en modal (vista accesible de cada gráfico) ──────
    const tablas = {};
    $(document).on('click', '[data-tabla]', function () {
        const t = tablas[$(this).data('tabla')];
        if (!t) return;
        $('#modal-datos-titulo').text(t.titulo);
        const filas = t.filas.slice().reverse();
        $('#tb-modal').html(
            '<thead><tr>' + t.columnas.map((c, i) => `<th class="${i ? 'num' : ''}">${esc(c)}</th>`).join('') + '</tr></thead><tbody>' +
            filas.map(f => '<tr>' + f.map((v, i) => `<td class="${i ? 'num' : ''}">${esc(v)}</td>`).join('') + '</tr>').join('') + '</tbody>'
        );
        bootstrap.Modal.getOrCreateInstance('#modal-datos').show();
    });

    // ─── AJAX ──────────────────────────────────────────────────────────
    const cache = {};
    function pedir(url, panel) {
        // Contador por panel: el estado «cargando» se quita cuando termina la última petición pendiente
        const $p = $(panel).each(function () { $(this).data('pend', ($(this).data('pend') || 0) + 1); }).addClass('is-loading');
        return $.ajax({ url, dataType: 'json' })
            .always(() => $p.each(function () {
                const n = $(this).data('pend') - 1;
                $(this).data('pend', n).toggleClass('is-loading', n > 0);
            }))
            .then((r) => r.success ? r.data : $.Deferred().reject(r));
    }

    /**
     * Como pedir(), pero si llega otra petición con la misma clave antes de que
     * esta responda, la respuesta vieja se descarta (evita pintar datos desordenados).
     */
    const turnos = {};
    function pedirUltimo(clave, url, panel) {
        const yo = turnos[clave] = (turnos[clave] || 0) + 1;
        const d = $.Deferred();
        pedir(url, panel)
            .done((r) => { if (turnos[clave] === yo) d.resolve(r); })
            .fail((x) => { if (turnos[clave] === yo) d.reject(x); });
        return d.promise();
    }

    /** Muestra el error sin destruir el lienzo del gráfico, con opción de reintentar. */
    const fallo = (sel, reintentar) => () => {
        const $box = $(sel).find('.chart-box, .chart-fill, .table-responsive, .heat-wrap').first();
        const $msg = $('<div class="empty"><i class="bi bi-exclamation-circle"></i>No se pudieron cargar los datos.</div>');
        if (reintentar) {
            $('<button type="button" class="btn-ghost mt-2"><i class="bi bi-arrow-clockwise me-1"></i>Reintentar</button>')
                .on('click', () => reintentar()).appendTo($('<div>').appendTo($msg));
        }
        if ($box.find('canvas').length) {
            $box.find('.chart-error').remove();
            $box.append($msg.addClass('chart-error'));
        } else {
            $box.empty().append($msg);
        }
    };

    // ═══ Resumen / KPIs ═════════════════════════════════════════════════
    function cargarResumen() {
        $.ajax({ url: ROUTES.resumen, dataType: 'json' })
            .done((r) => pintarResumen(r.data))
            .fail((x) => {
                $('#hero-mes').text(x.responseJSON?.message ?? 'Sin datos');
            });
    }

    function pintarResumen(d) {
        $('#hero-mes').text('IPC a ' + mesTxt(d.mes));
        const m = d.meta, ia = d.ipc.interanual;

        $('#k-inter').html(pct(ia) );
        const estado = ia < m.min ? ['bajo', 'bi-arrow-down', 'Bajo la meta'] : (ia > m.max ? ['sobre', 'bi-arrow-up', 'Sobre la meta'] : ['dentro', 'bi-check-circle', 'Dentro de la meta']);
        $('#k-meta').attr('class', 'badge-meta ' + estado[0]).html(`<i class="bi ${estado[1]}"></i>${estado[2]} (${m.min}–${m.max} %)`);

        $('#k-mensual').html(pct(d.ipc.mensual));
        $('#k-acum').text(pct(d.ipc.acumulada));

        $('#k-sub').html(d.subyacente ? pct(d.subyacente.interanual) : '—');
        $('#k-exp').text(d.expectativa ? nf(2).format(d.expectativa.valor) + ' %' : '—');
        if (d.subyacente && d.subyacente.mes !== d.mes) $('#k-sub').append(` <small>${mesCor(d.subyacente.mes)}</small>`);

        if (d.canasta) {
            $('#k-cba').text(crc(d.canasta.costo));
            $('#k-cba-foot').html(`${usd(d.canasta.usd)} · ${delta(d.canasta.interanual)} anual`);
            $('#k-lp').text(crc(d.canasta.linea_pobreza));
            $('#k-lp-foot').text('per cápita · ' + mesTxt(d.canasta.mes));
        } else {
            $('#k-cba-foot, #k-lp-foot').text('Sin datos del INEC');
        }

        if (d.salario) {
            $('#k-sal').html(pct(d.salario.interanual));
            $('#k-sal-foot').html(`${delta(d.salario.interanual, d.salario.interanual >= 0 ? 'gana a la inflación' : 'pierde con la inflación', true)}`);
        }

        explicarKpis(d, estado[2]);
    }

    // ─── Explicación de las tarjetas con los números de hoy (lenguaje para niños) ──
    const cuanto = (v) => Math.abs(v) < 0.5 ? 'un poquitito' : (Math.abs(v) < 2 ? 'un poco' : (Math.abs(v) < 5 ? 'bastante' : 'muchísimo'));
    const conPct = (base, v) => crc(base * (1 + v / 100));

    function explicarKpis(d, textoMeta) {
        const ia = d.ipc.interanual, m = d.meta, mes = mesTxt(d.mes), hace = mesTxt(restarMeses(d.mes, 12));
        const items = [];

        // 1. Inflación interanual
        const zona = ia < m.min
            ? `Eso es <strong>menos</strong> que la meta: los precios están más tranquilos de lo normal, casi quietos.`
            : (ia > m.max
                ? `Eso es <strong>más</strong> que la meta: los precios suben muy rápido, como cuando alguien tiene fiebre.`
                : `Eso está <strong>justo dentro</strong> de la meta: los precios suben a un ritmo sano.`);
        items.push({
            id: 'inter', ico: 'graph-up-arrow', titulo: 'Inflación interanual', num: pct(ia),
            hoy: ia >= 0
                ? `Lo que en ${hace} costaba ₡10 000, en ${mes} cuesta ${conPct(10000, ia)}.`
                : `Lo que en ${hace} costaba ₡10 000, en ${mes} cuesta ${conPct(10000, ia)}: ¡más barato!`,
            texto: `Comparamos los precios de <strong>${mes}</strong> con los de <strong>${hace}</strong>. Las cosas ${ia >= 0 ? 'subieron' : 'bajaron'} ${cuanto(ia)}: lo que costaba <strong>₡10 000</strong>, ahora cuesta <strong>${conPct(10000, ia)}</strong>.`,
            lineas: [[textoMeta + ` (${m.min}–${m.max} %)`, `El Banco Central quiere que los precios suban entre ${m.min} % y ${m.max} % cada año, ni más ni menos. ${zona}`]],
        });

        // 2. Variación mensual y acumulada
        const vm = d.ipc.mensual, ac = d.ipc.acumulada;
        items.push({
            id: 'mensual', ico: 'calendar-month', titulo: 'Variación mensual', num: pct(vm),
            hoy: `Del mes pasado a ${mes}, ₡10 000 en compras pasaron a costar ${conPct(10000, vm)}.`,
            texto: `Ahora comparamos con el <strong>mes pasado</strong>. Los precios ${vm > 0 ? 'subieron' : (vm < 0 ? 'bajaron' : 'no cambiaron')} ${vm ? cuanto(vm) : ''}: ₡10 000 en compras pasaron a costar <strong>${conPct(10000, vm)}</strong>.`,
            lineas: [[`Acumulada en el año: ${pct(ac)}`, `Es todo lo que cambiaron los precios desde que empezó el año (desde diciembre). ${ac < 0
                ? 'Como es negativa, las cosas están un poquito <strong>más baratas</strong> que al empezar el año' + (vm > 0 ? ', aunque este mes subieron un poco.' : '.')
                : 'Como es positiva, las cosas están <strong>más caras</strong> que al empezar el año.'}`]],
        });

        // 3. Subyacente y expectativa
        if (d.subyacente) {
            const s = d.subyacente.interanual, lineas = [];
            if (d.subyacente.mes !== d.mes) {
                lineas.push([mesCor(d.subyacente.mes), `Ese papelito dice de qué mes es el dato: <strong>${mesTxt(d.subyacente.mes)}</strong>. El Banco Central publica este número unos meses después que los demás.`]);
            }
            if (d.expectativa) {
                lineas.push([`Expectativa 12 m: ${nf(2).format(d.expectativa.valor)} %`, `Es una adivinanza de los expertos: creen que dentro de un año las cosas costarán <strong>${nf(2).format(d.expectativa.valor)} %</strong> más. O sea, ₡10 000 pasarían a ${conPct(10000, d.expectativa.valor)}.`]);
            }
            items.push({
                id: 'sub', ico: 'bullseye', titulo: 'Subyacente', num: pct(s),
                hoy: `Sin los precios «montaña rusa», las cosas están ${Math.abs(s) < 0.3 ? 'casi iguales que' : (s > 0 ? 'más caras que' : 'más baratas que')} hace un año (${pct(s)}).`,
                texto: `Si dejamos de lado los precios que suben y bajan como <strong>montaña rusa</strong> (gasolina, verduras), los demás están ${Math.abs(s) < 0.3 ? '<strong>casi iguales</strong> que' : (s > 0 ? '<strong>más caros</strong> que' : '<strong>más baratos</strong> que')} hace un año. Sirve para saber si la subida de precios es de verdad o solo un brinco pasajero.`,
                lineas,
            });
        }

        // 4. Canasta básica
        if (d.canasta) {
            const c = d.canasta, antes = c.interanual != null ? c.costo / (1 + c.interanual / 100) : null;
            const lineas = [];
            if (c.usd) lineas.push([usd(c.usd), `Es lo mismo pero en <strong>dólares</strong>: si pagaras con dólares, necesitarías ${usd(c.usd)} para llenar la lonchera.`]);
            if (c.interanual != null) lineas.push([`${pct(c.interanual)} anual`, c.interanual < 0
                ? `La lonchera está <strong>más barata</strong> que hace un año: antes costaba ${crc(antes)} y ahora ${crc(c.costo)}, o sea <strong>${crc(antes - c.costo)} menos</strong>. Por eso la flechita es verde: ¡buena noticia!`
                : `La lonchera está <strong>más cara</strong> que hace un año: antes costaba ${crc(antes)} y ahora ${crc(c.costo)}, o sea <strong>${crc(c.costo - antes)} más</strong>. Por eso la flechita es roja.`]);
            items.push({
                id: 'cba', ico: 'basket', titulo: 'Canasta básica', num: crc(c.costo),
                hoy: `La comida de una persona para un mes cuesta ${crc(c.costo)}; para una familia de 4, ${crc(c.costo * 4)}.`,
                texto: `Llenar la <strong>lonchera</strong> con toda la comida que <strong>una persona</strong> necesita en un mes cuesta <strong>${crc(c.costo)}</strong>. Para una familia de 4 personas serían <strong>${crc(c.costo * 4)}</strong>.`,
                lineas,
            });

            // 5. Línea de pobreza
            if (c.linea_pobreza) {
                const lp = c.linea_pobreza;
                items.push({
                    id: 'lp', ico: 'house-heart', titulo: 'Línea de pobreza', num: crc(lp),
                    hoy: `Una persona necesita al menos ${crc(lp)} al mes para comida, casa, ropa y bus.`,
                    texto: `Es lo mínimo que una persona necesita al mes para <strong>todo</strong>: comida, casa, ropa, luz, bus y escuela. Si alguien tiene menos que <strong>${crc(lp)}</strong>, se dice que vive en pobreza. La comida (${crc(c.costo)}) es como <strong>${nf(0).format(c.costo / lp * 100)} de cada 100 colones</strong> de ese monto.`,
                    lineas: [
                        ['per cápita', `Quiere decir <strong>por cada persona</strong>. Una familia de 4 necesitaría ${crc(lp * 4)} al mes.`],
                        [mesTxt(c.mes), 'Es el mes del dato más nuevo que publicó el INEC.'],
                    ],
                });
            }
        }

        // 6. Salario mínimo real
        if (d.salario) {
            const s = d.salario.interanual;
            items.push({
                id: 'sal', ico: 'wallet2', titulo: 'Salario mínimo real', num: pct(s),
                hoy: s >= 0
                    ? `Con el sueldo mínimo se compra ${nf(2).format(Math.abs(s))} % más que hace un año: si antes alcanzaba para 100 panes, ahora para ${nf(0).format(100 + s)}.`
                    : `Con el sueldo mínimo se compra ${nf(2).format(Math.abs(s))} % menos que hace un año.`,
                texto: `El <strong>salario mínimo</strong> es lo menos que se le puede pagar a alguien por trabajar. Hoy, con ese sueldo se pueden comprar <strong>${nf(2).format(Math.abs(s))} %</strong> ${s >= 0 ? 'más' : 'menos'} cosas que hace un año: si antes alcanzaba para <strong>100 panes</strong>, ahora alcanza para <strong>${nf(0).format(100 + s)}</strong>.`,
                lineas: [[s >= 0 ? 'gana a la inflación' : 'pierde con la inflación', s >= 0
                    ? 'Es como una carrera: el sueldo subió <strong>más rápido</strong> que los precios. Verde = buena noticia.'
                    : 'Es como una carrera: los precios subieron <strong>más rápido</strong> que el sueldo. Rojo = mala noticia.']],
            });
        }

        $('#kpi-exp').html(items.map(i => `<div class="kpi-exp-item">
            <h4><i class="bi bi-${i.ico}"></i> ${esc(i.titulo)} <span class="kpi-exp-num">${i.num}</span></h4>
            <p>${i.texto}</p>
            ${i.lineas.length ? '<ul class="kpi-exp-lineas">' + i.lineas.map(([cita, exp]) => `<li><span class="cita">${cita}</span> ${exp}</li>`).join('') + '</ul>' : ''}
        </div>`).join(''));

        // Agrega «Hoy:» al popover de cada tarjeta
        const porId = Object.fromEntries(items.map(i => [i.id, i.hoy]));
        $('.kpi .kpi-help').each(function () {
            const k = $(this).closest('.kpi').find('.kpi-value').attr('id').replace('k-', '');
            const pop = bootstrap.Popover.getInstance(this);
            if (pop && porId[k]) {
                pop.setContent({ '.popover-header': this.dataset.bsTitle, '.popover-body': `${esc($(this).data('base'))}<hr class="my-2"><strong>Hoy:</strong> ${porId[k]}` });
            }
        });
    }

    const restarMeses = (ym, n) => {
        const t = +ym.slice(0, 4) * 12 + (+ym.slice(5, 7) - 1) - n;
        return `${Math.floor(t / 12)}-${String(t % 12 + 1).padStart(2, '0')}`;
    };

    // ═══ 1. Inflación general ══════════════════════════════════════════
    let rangoMeses = 60;
    const recorte = (arr) => rangoMeses ? arr.slice(-rangoMeses) : arr;

    function cargarIpc() {
        $.when(pedir(ROUTES.ipc, '#p-ipc'), pedir(ROUTES.componentes, '#p-comp'))
            .done((ipc, comp) => { cache.ipc = ipc; cache.comp = comp; pintarIpc(); pintarComp(); llenarCalculadora(); })
            .fail(fallo('#p-ipc', cargarIpc));
    }

    function pintarIpc() {
        const d = cache.ipc; if (!d) return;
        const meses = recorte(d.meses);
        const ds = [
            linea('IPC general', recorte(d.interanual), T.s[0], { borderWidth: 2.5 }),
            linea('Subyacente', recorte(d.subyacente), T.s[1]),
            linea('Expectativa 12 m', recorte(d.expectativa), T.s[2], { borderDash: [5, 4] }),
        ];
        const c = grafico('ch-ipc', {
            type: 'line',
            data: { labels: meses.map(mesCor), datasets: ds },
            options: opcionesLinea((v) => nf(0).format(v) + ' %', (v) => pct(v), { metaBand: d.meta }),
        });
        c.$titulos = meses.map(mesTxt);
        leyenda('#lg-ipc', [
            { label: 'IPC general', color: T.s[0] }, { label: 'Subyacente', color: T.s[1] },
            { label: 'Expectativa 12 m', color: T.s[2], dash: true }, { label: `Meta BCCR ${d.meta.min}–${d.meta.max} %`, band: true },
        ]);
        tablas.ipc = {
            titulo: 'Inflación interanual (%)', columnas: ['Mes', 'IPC interanual', 'IPC mensual', 'Subyacente', 'Expectativa 12 m'],
            filas: d.meses.map((m, i) => [mesTxt(m), pct(d.interanual[i]), pct(d.mensual[i]), pct(d.subyacente[i]), d.expectativa[i] == null ? '—' : nf(2).format(d.expectativa[i]) + ' %']),
        };
    }

    // Color fijo por entidad: Regulados s1, No regulados s2, Bienes s3, Servicios s4
    const COMP = { '89640': 0, '89645': 1, '89650': 2, '89653': 3 };
    const PARES = { reg: ['89640', '89645'], bys: ['89650', '89653'] };
    let par = 'reg';

    function pintarComp() {
        const d = cache.comp; if (!d) return;
        const meses = recorte(d.meses);
        const series = d.series.filter(s => PARES[par].includes(s.codigo));
        const c = grafico('ch-comp', {
            type: 'line',
            data: { labels: meses.map(mesCor), datasets: series.map(s => linea(s.corto, recorte(s.valores), T.s[COMP[s.codigo]])) },
            options: opcionesLinea((v) => nf(0).format(v) + ' %', (v) => pct(v, 1)),
        });
        c.$titulos = meses.map(mesTxt);
        leyenda('#lg-comp', series.map(s => ({ label: s.corto, color: T.s[COMP[s.codigo]] })));

        const ult = series.map(s => ({ n: s.corto.toLowerCase(), v: s.valores[s.valores.length - 1] }));
        const [a, b] = ult;
        const mayor = a.v > b.v ? a : b;
        $('#ins-comp span').html(`En ${mesTxt(d.meses[d.meses.length - 1])} los <strong>${esc(a.n)}</strong> variaron ${pct(a.v)} y los <strong>${esc(b.n)}</strong> ${pct(b.v)}: la presión viene de los <strong>${esc(mayor.n)}</strong>.`);
        tablas.comp = {
            titulo: 'Inflación interanual por componente (%)', columnas: ['Mes', ...d.series.map(s => s.corto)],
            filas: d.meses.map((m, i) => [mesTxt(m), ...d.series.map(s => pct(s.valores[i]))]),
        };
    }

    $('#rango-ipc').on('click', 'button', function () {
        $(this).addClass('active').siblings().removeClass('active');
        rangoMeses = +$(this).data('meses');
        pintarIpc(); pintarComp();
    });
    $('#seg-comp').on('click', 'button', function () {
        $(this).addClass('active').siblings().removeClass('active');
        par = $(this).data('par'); pintarComp();
    });

    // ═══ 2. Divisiones ═════════════════════════════════════════════════
    function cargarDivisiones(mes) {
        pedirUltimo('div', ROUTES.divisiones + (mes ? '?mes=' + mes : ''), '#p-div, #p-tabla-div, #p-heat')
            .done((d) => {
                cache.div = d;
                if (!$('#sel-mes-div option').length) {
                    $('#sel-mes-div').html(d.meses.map(m => `<option value="${m}">${mesTxt(m)}</option>`).join(''));
                    const base = d.meses.includes('2020-12') ? '2020-12' : d.meses[d.meses.length - 1];
                    $('#sel-base').html(d.meses.slice().reverse().map(m => `<option value="${m}" ${m === base ? 'selected' : ''}>Base ${mesTxt(m)}</option>`).join(''));
                    construirChips(d.filas);
                    cargarEvolucion();
                }
                $('#sel-mes-div').val(d.mes);
                pintarDivisiones();
            })
            .fail(fallo('#p-div', () => cargarDivisiones($('#sel-mes-div').val())));
    }

    function pintarDivisiones() {
        const d = cache.div; if (!d) return;
        barrasDivergentes('ch-div', d.filas.map(f => f.corto), d.filas.map(f => f.interanual), (v) => pct(v),
            { axis: 'x', value: d.general.interanual, label: 'IPC ' + pct(d.general.interanual) });
        charts['ch-div'].$titulos = d.filas.map(f => f.nombre);
        $('#sub-div').text('Variación interanual · ' + mesTxt(d.mes));

        const fila = (ico, nombre, f, bold) => `<tr class="${bold ? 'fw-bold' : ''}">
            <td><div class="d-flex align-items-center gap-2"><span class="cat-ico"><i class="bi bi-${ico}"></i></span>${esc(nombre)}</div></td>
            <td class="num ${clsVar(f.mensual)}">${pct(f.mensual)}</td>
            <td class="num ${clsVar(f.interanual)}">${pct(f.interanual)}</td>
            <td class="num ${clsVar(f.acumulada)}">${pct(f.acumulada)}</td></tr>`;
        $('#tb-div tbody').html(fila('bar-chart-line', 'IPC general', d.general, true) + d.filas.map(f => fila(f.icono || 'tag', f.nombre, f)).join(''));

        pintarCalor(d.calor);
    }

    function pintarCalor(c) {
        const todos = c.filas.flatMap(f => f.valores).concat(c.general).filter(v => v != null);
        const max = Math.max(2, Math.min(15, Math.max(...todos.map(Math.abs))));
        const celda = (v, nombre, m) => {
            const bg = colorDiv(v, max);
            return `<td style="background:${bg};color:${tintaSobre(bg)}" title="${esc(nombre)} · ${mesTxt(m)}: ${pct(v)}">${v == null ? '—' : nf(1).format(v)}</td>`;
        };
        $('#heat').html(
            '<thead><tr><th></th>' + c.meses.map(m => `<th>${mesCor(m)}</th>`).join('') + '</tr></thead><tbody>' +
            c.filas.map(f => `<tr><th class="fila">${esc(f.corto)}</th>${f.valores.map((v, i) => celda(v, f.corto, c.meses[i])).join('')}</tr>`).join('') +
            `<tr class="general"><th class="fila">IPC general</th>${c.general.map((v, i) => celda(v, 'IPC general', c.meses[i])).join('')}</tr></tbody>`
        );
        $('#heat-ramp').css('background', `linear-gradient(90deg, ${T.neg}, ${T.mid}, ${T.pos})`);
    }

    $('#sel-mes-div').on('change', function () { cargarDivisiones($(this).val()); });

    // Evolución comparada: el color sigue a la división mientras esté seleccionada
    const MAX_SEL = 4;
    const seleccion = new Map([['89668', 0], ['89686', 1]]);
    let modoEvo = 'indice';

    function construirChips(filas) {
        const orden = filas.slice().sort((a, b) => a.corto.localeCompare(b.corto, 'es'));
        $('#chips-div').html(orden.map(f => `<button type="button" class="chip" data-codigo="${f.codigo}" aria-pressed="false"><span class="dot"></span>${esc(f.corto)}</button>`).join(''));
        pintarChips();
    }

    function pintarChips() {
        $('#chips-div .chip').each(function () {
            const cod = String($(this).data('codigo')), on = seleccion.has(cod);
            const color = on ? T.s[seleccion.get(cod)] : '';
            $(this).toggleClass('on', on).attr('aria-pressed', on).css('color', on ? color : '')
                .prop('disabled', !on && seleccion.size >= MAX_SEL)
                .find('.dot').css('background', on ? color : '');
        });
    }

    $('#chips-div').on('click', '.chip', function () {
        const cod = String($(this).data('codigo'));
        if (seleccion.has(cod)) {
            seleccion.delete(cod);
        } else if (seleccion.size < MAX_SEL) {
            const usados = new Set(seleccion.values());
            seleccion.set(cod, [0, 1, 2, 3].find(i => !usados.has(i)));
        }
        pintarChips(); cargarEvolucion();
    });
    $('#seg-evo').on('click', 'button', function () {
        $(this).addClass('active').siblings().removeClass('active');
        modoEvo = $(this).data('modo');
        $('#sel-base').prop('disabled', modoEvo !== 'indice');
        cargarEvolucion();
    });
    $('#sel-base').on('change', cargarEvolucion);

    function cargarEvolucion() {
        const q = $.param({ codigos: [...seleccion.keys()].join(','), modo: modoEvo, base: $('#sel-base').val() });
        pedirUltimo('evo', ROUTES.evolucion + '?' + q, '#p-evo')
            .done((d) => { cache.evo = d; pintarEvolucion(); })
            .fail(fallo('#p-evo', cargarEvolucion));
    }

    function pintarEvolucion() {
        const d = cache.evo; if (!d) return;
        const indice = d.modo === 'indice';
        const fmt = indice ? (v) => nf(1).format(v) : (v) => pct(v, 1);
        // Red de seguridad: una serie sin color asignado usa el neutro en vez de quedar invisible
        const colorDe = (s) => s.codigo === '89635' ? T.neutral : (T.s[seleccion.get(s.codigo)] ?? T.neutral);
        const c = grafico('ch-evo', {
            type: 'line',
            data: {
                labels: d.meses.map(mesCor),
                datasets: d.series.map(s => linea(s.corto, s.valores, colorDe(s), s.codigo === '89635' ? { borderWidth: 2.5 } : {})),
            },
            options: opcionesLinea(indice ? (v) => nf(0).format(v) : (v) => nf(0).format(v) + ' %', fmt,
                indice ? { refLine: { axis: 'y', value: 100, label: 'Base 100 · ' + mesTxt(d.base) } } : {}),
        });
        c.$titulos = d.meses.map(mesTxt);
        leyenda('#lg-evo', d.series.map(s => ({ label: s.corto, color: colorDe(s) })));
        tablas.evo = {
            titulo: indice ? `Índice (base ${mesTxt(d.base)} = 100)` : 'Variación interanual (%)',
            columnas: ['Mes', ...d.series.map(s => s.corto)],
            filas: d.meses.map((m, i) => [mesTxt(m), ...d.series.map(s => fmt(s.valores[i]))]),
        };
    }

    // ═══ 3. Canasta básica ══════════════════════════════════════════════
    let moneda = 'crc';

    function cargarCanasta() {
        pedir(ROUTES.canasta, '#p-cba, #p-cba-ipc').done((d) => {
            cache.cba = d;
            if (!d.disponible) { $('#cba-vacio').removeClass('d-none'); $('#cba-contenido').addClass('d-none'); return; }
            pintarCanasta(); pintarCbaIpc();
            cargarSubgrupos();
        }).fail(fallo('#p-cba', cargarCanasta));
    }

    function pintarCanasta() {
        const d = cache.cba; if (!d?.disponible) return;
        const u = moneda === 'usd', sfx = u ? '_usd' : '';
        const fmt = u ? usd : crc;
        const series = [
            ['Nacional', d['nacional' + sfx], T.s[0], { borderWidth: 2.5 }],
            ['Urbana', d['urbana' + sfx], T.s[1]],
            ['Rural', d['rural' + sfx], T.s[2]],
        ];
        const c = grafico('ch-cba', {
            type: 'line',
            data: { labels: d.meses.map(mesCor), datasets: series.map(([l, v, col, x]) => linea(l, v, col, x)) },
            options: opcionesLinea((v) => u ? '$' + nf(0).format(v) : '₡' + nf(0).format(v / 1000) + 'k', fmt),
        });
        c.$titulos = d.meses.map(mesTxt);
        leyenda('#lg-cba', series.map(([l, , col]) => ({ label: l, color: col })));
        $('#sub-cba').text('Per cápita mensual · ' + (u ? 'dólares (TC venta promedio del mes)' : 'colones corrientes'));
        tablas.cba = {
            titulo: 'Costo per cápita de la CBA', columnas: ['Mes', 'Nacional ₡', 'Urbana ₡', 'Rural ₡', 'Nacional US$', 'Línea de pobreza ₡'],
            filas: d.meses.map((m, i) => [mesTxt(m), crc(d.nacional[i]), crc(d.urbana[i]), crc(d.rural[i]), usd(d.nacional_usd[i]), crc(d.linea_pobreza[i])]),
        };
    }

    function pintarCbaIpc() {
        const d = cache.cba; if (!d?.disponible) return;
        const inicio = d.interanual_alimentos.findIndex(v => v != null);
        if (inicio < 0) return;
        const meses = d.meses.slice(inicio), cba = d.interanual_cba.slice(inicio), ali = d.interanual_alimentos.slice(inicio);
        const c = grafico('ch-cbaipc', {
            type: 'line',
            data: { labels: meses.map(mesCor), datasets: [linea('Canasta INEC', cba, T.s[0]), linea('IPC alimentos BCCR', ali, T.s[1])] },
            options: opcionesLinea((v) => nf(0).format(v) + ' %', (v) => pct(v, 1)),
        });
        c.$titulos = meses.map(mesTxt);
        leyenda('#lg-cbaipc', [{ label: 'Canasta INEC', color: T.s[0] }, { label: 'IPC alimentos BCCR', color: T.s[1] }]);

        // Correlación de Pearson sobre los meses con ambas series
        const pares = cba.map((v, i) => [v, ali[i]]).filter(([x, y]) => x != null && y != null);
        const n = pares.length, mx = pares.reduce((s, p) => s + p[0], 0) / n, my = pares.reduce((s, p) => s + p[1], 0) / n;
        const cov = pares.reduce((s, [x, y]) => s + (x - mx) * (y - my), 0);
        const r = cov / Math.sqrt(pares.reduce((s, [x]) => s + (x - mx) ** 2, 0) * pares.reduce((s, [, y]) => s + (y - my) ** 2, 0));
        const k = cba.map((v, i) => v != null && ali[i] != null).lastIndexOf(true);
        const dif = cba[k] - ali[k];
        $('#ins-cba span').html(`El INEC actualiza el costo de la canasta con precios del IPC, por eso ambas series casi coinciden (correlación <strong>${nf(3).format(r)}</strong> en ${n} meses). `
            + `La diferencia viene de la composición: en ${mesTxt(meses[k])} la canasta varió <strong>${pct(cba[k], 1)}</strong> y el IPC de alimentos <strong>${pct(ali[k], 1)}</strong>, `
            + `${Math.abs(dif) < .1 ? 'prácticamente igual' : (dif < 0 ? 'la canasta bajó ' + nf(1).format(-dif) + ' p.p. más' : 'la canasta subió ' + nf(1).format(dif) + ' p.p. más')}.`);
        tablas.cbaipc = {
            titulo: 'Variación interanual: canasta INEC vs IPC alimentos (%)', columnas: ['Mes', 'Canasta INEC', 'IPC alimentos'],
            filas: meses.map((m, i) => [mesTxt(m), pct(cba[i]), pct(ali[i])]),
        };
    }

    $('#seg-moneda').on('click', 'button', function () {
        $(this).addClass('active').siblings().removeClass('active');
        moneda = $(this).data('moneda'); pintarCanasta();
    });

    function cargarSubgrupos(mes) {
        pedirUltimo('sg', ROUTES.subgrupos + (mes ? '?mes=' + mes : ''), '#p-sg').done((d) => {
            cache.sg = d;
            if (!d.disponible) return;
            if (!$('#sel-mes-sg option').length) {
                $('#sel-mes-sg').html(d.meses.map(m => `<option value="${m}">${mesTxt(m)}</option>`).join(''));
            }
            $('#sel-mes-sg').val(d.mes);
            pintarSubgrupos();
        }).fail(fallo('#p-sg', () => cargarSubgrupos($('#sel-mes-sg').val())));
    }

    function pintarSubgrupos() {
        const d = cache.sg; if (!d?.disponible) return;
        barrasDivergentes('ch-sg', d.filas.map(f => f.nombre), d.filas.map(f => f.contribucion), pp);
        $('#sub-sg').html(`CBA ${mesTxt(d.mes)}: <strong>${crc(d.cba.costo)}</strong> per cápita · ${delta(d.cba.interanual)} interanual. Contribución de cada subgrupo en puntos porcentuales; suman la variación total.`);
        const maxPeso = Math.max(...d.filas.map(f => f.peso));
        $('#tb-sg tbody').html(d.filas.map(f => `<tr>
            <td><div class="d-flex align-items-center gap-2"><span class="cat-ico"><i class="bi bi-${f.icono || 'basket'}"></i></span>${esc(f.nombre)}</div></td>
            <td class="num">${crc(f.costo)}</td>
            <td><div class="d-flex align-items-center gap-2"><div class="bar-mini flex-grow-1"><div style="width:${(f.peso / maxPeso * 100).toFixed(1)}%"></div></div><span class="small text-2 tabular">${nf(1).format(f.peso)}%</span></div></td>
            <td class="num ${clsVar(f.interanual)}">${pct(f.interanual, 1)}</td>
            <td class="num ${clsVar(f.contribucion)}">${pp(f.contribucion)}</td></tr>`).join(''));
    }

    $('#sel-mes-sg').on('change', function () { cargarSubgrupos($(this).val()); });

    // ═══ 4. Poder adquisitivo ═══════════════════════════════════════════
    function llenarCalculadora() {
        const meses = cache.ipc?.meses ?? [];
        if (!meses.length || $('#calc-desde option').length) return;
        const def = meses[Math.max(0, meses.length - 61)];
        $('#calc-desde').html(meses.slice().reverse().map(m => `<option value="${m}" ${m === def ? 'selected' : ''}>${mesTxt(m)}</option>`).join(''));
        $('#form-calc').trigger('submit');
    }

    $('#form-calc').on('submit', function (e) {
        e.preventDefault();
        const monto = parseFloat($('#calc-monto').val());
        if (!(monto > 0)) { $('#calc-eq').text('—'); $('#calc-det').text('Ingrese un monto mayor que cero.'); return; }
        pedirUltimo('poder', ROUTES.poder + '?' + $.param({ monto, desde: $('#calc-desde').val() }), '#p-calc')
            .done((d) => {
                $('#calc-eq').text(crc(d.equivalente));
                $('#calc-det').html(`Los precios ${d.variacion >= 0 ? 'subieron' : 'bajaron'} <strong>${pct(d.variacion)}</strong> entre ${mesTxt(d.desde)} y ${mesTxt(d.hasta)}. Con ${crc(d.monto)} hoy se compra lo que con ${crc(d.poder)} en ${mesTxt(d.desde)}.`);
            })
            .fail((x) => { $('#calc-eq').text('—'); $('#calc-det').text(x.responseJSON?.error ?? 'No se pudo calcular.'); });
    });

    function cargarSalario() {
        pedir(ROUTES.salario, '#p-sal').done((d) => { cache.sal = d; pintarSalario(); }).fail(fallo('#p-sal', cargarSalario));
    }

    function pintarSalario() {
        const d = cache.sal; if (!d) return;
        const ini = d.interanual.findIndex(v => v != null);
        const meses = d.meses.slice(ini), vals = d.interanual.slice(ini);
        const c = grafico('ch-sal', {
            type: 'line',
            data: { labels: meses.map(mesCor), datasets: [linea('Salario mínimo real', vals, T.s[0], { fill: { target: 'origin', above: T.s[0] + '1f', below: T.pos + '1f' } })] },
            options: opcionesLinea((v) => nf(0).format(v) + ' %', (v) => pct(v), { refLine: { axis: 'y', value: 0 } }),
        });
        c.$titulos = meses.map(mesTxt);
        const k = vals.length - 1, positivos = vals.filter(v => v > 0).length;
        $('#ins-sal span').html(`En ${mesTxt(meses[k])} el salario mínimo real ${vals[k] >= 0 ? 'creció' : 'cayó'} <strong>${pct(vals[k])}</strong>: ${vals[k] >= 0 ? 'los salarios mínimos subieron más que los precios' : 'los precios subieron más que los salarios mínimos'}. Le ganó a la inflación en ${positivos} de ${vals.length} meses.`);
        tablas.sal = {
            titulo: 'Salario mínimo real', columnas: ['Mes', 'Índice', 'Variación interanual'],
            filas: d.meses.map((m, i) => [mesTxt(m), nf(2).format(d.nivel[i]), pct(d.interanual[i])]),
        };
    }

    // ═══ Tema claro / oscuro ════════════════════════════════════════════
    const temaActual = () => document.documentElement.dataset.theme
        || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const iconoTema = () => $('#btn-tema i').attr('class', temaActual() === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars');

    function repintarTodo() {
        T = tokens(); defaults();
        pintarIpc(); pintarComp(); pintarDivisiones(); pintarEvolucion(); pintarChips();
        pintarCanasta(); pintarCbaIpc(); pintarSubgrupos(); pintarSalario();
    }

    $('#btn-tema').on('click', function () {
        const nuevo = temaActual() === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nuevo;
        try { localStorage.setItem('tema', nuevo); } catch (e) {}
        iconoTema(); repintarTodo();
    });
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (!document.documentElement.dataset.theme) { iconoTema(); repintarTodo(); }
    });
    iconoTema();

    // Sub-nav activa según la sección visible
    const obs = new IntersectionObserver((ents) => ents.forEach(e => {
        if (e.isIntersecting) $('#subnav .nav-link').removeClass('active').filter(`[href="#${e.target.id}"]`).addClass('active');
    }), { rootMargin: '-40% 0px -55% 0px' });
    document.querySelectorAll('main .section').forEach(s => obs.observe(s));

    // ─── Ayuda: popovers de las tarjetas (hover en escritorio, toque en móvil) ──
    document.querySelectorAll('.kpi [data-bs-toggle="popover"]').forEach((el) => {
        $(el).data('base', el.dataset.bsContent);
        new bootstrap.Popover(el, { trigger: 'hover focus', placement: 'bottom', container: 'body', html: true });
        el.addEventListener('click', () => el.focus());
    });

    // ─── Arranque ───────────────────────────────────────────────────────
    cargarResumen();
    cargarIpc();
    cargarDivisiones();
    cargarCanasta();
    cargarSalario();
});
