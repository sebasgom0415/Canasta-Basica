<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Administración · Canasta Básica CR</title>
    <script>
        try { const t = localStorage.getItem('tema'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}?v={{ filemtime(public_path('assets/css/dashboard.css')) }}">
</head>
<body>

<header class="hero" style="padding-bottom:2.2rem">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="hero-eyebrow"><i class="bi bi-shield-lock me-1"></i> Panel de administración</div>
            <h1 class="hero-title mb-0">Fuentes de datos</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-hero" href="{{ route('home') }}"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
            @if ($authenticated)
                <a class="btn btn-hero" href="{{ route('admin.logout') }}"><i class="bi bi-box-arrow-right me-1"></i> Salir</a>
            @endif
        </div>
    </div>
</header>

<main class="container py-4">
@if (!$authenticated)
    <div class="row justify-content-center">
        <div class="col-sm-8 col-md-5">
            <form class="panel" method="POST" action="{{ route('admin.login') }}">
                @csrf
                <h2 class="panel-title mb-3">Ingresar</h2>
                <label for="key" class="form-label small text-2">Clave de administración</label>
                <input type="password" class="form-control sel mb-2" id="key" name="key" required autofocus autocomplete="current-password">
                @error('key') <div class="small var-pos mb-2">{{ $message }}</div> @enderror
                <button class="btn btn-accent w-100">Entrar</button>
            </form>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><span class="source-tag me-1">BCCR</span> Sincronizar indicadores</h2>
                        <p class="panel-sub">API SDDE · cuadros 1, 51, 52, 53, 680, 792 y 793. Corre solo a diario con <code>php artisan schedule:run</code>.</p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-accent" id="btn-sync" data-completo="0">
                        <i class="bi bi-arrow-repeat me-1"></i> Últimos 18 meses
                    </button>
                    <button class="btn-ghost" id="btn-sync-full" data-completo="1"><i class="bi bi-clock-history me-1"></i> Historial completo (2015 →)</button>
                </div>
                <div class="small mt-3" id="msg-sync" aria-live="polite"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><span class="source-tag me-1">INEC</span> Importar Canasta Básica</h2>
                        <p class="panel-sub">
                            Descargue de <a href="https://inec.cr/tematicas/listado?topics=134,135" target="_blank" rel="noopener">inec.cr</a>
                            el Excel <em>«Cuadros de series históricas de resultados de la CBA»</em> (seEconomCBA…xlsx) y súbalo aquí.
                        </p>
                    </div>
                </div>
                <form id="form-cba" class="d-flex flex-wrap gap-2" enctype="multipart/form-data">
                    <input type="file" class="form-control sel flex-grow-1" name="archivo" accept=".xlsx" required style="width:auto">
                    <button class="btn btn-sm btn-accent"><i class="bi bi-upload me-1"></i> Importar</button>
                </form>
                <div class="small mt-3" id="msg-cba" aria-live="polite"></div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Estado por indicador</h2></div>
                <div class="table-responsive">
                    <table class="table table-clean" id="tb-estado">
                        <thead><tr><th>Indicador</th><th>Fuente</th><th class="num">Datos</th><th class="num">Desde</th><th class="num">Hasta</th></tr></thead>
                        <tbody><tr><td colspan="5" class="text-muted-2">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Últimas sincronizaciones</h2></div>
                <div class="table-responsive">
                    <table class="table table-clean" id="tb-log">
                        <thead><tr><th>Fecha</th><th>Detalle</th><th class="num">Reg.</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
</main>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
@if ($authenticated)
<script>
$(function () {
    const R = {
        estado:      '{{ route('api.admin.estado') }}',
        sincronizar: '{{ route('api.admin.sincronizar') }}',
        canasta:     '{{ route('api.admin.canasta') }}',
    };
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' } });
    const esc = (s) => $('<div>').text(s ?? '').html();
    const nf  = new Intl.NumberFormat('es-CR');
    const msg = (sel, ok, txt) => $(sel).html(`<span class="${ok ? 'var-neg' : 'var-pos'}"><i class="bi ${ok ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-1"></i>${esc(txt)}</span>`);
    const errorDe = (x) => x.responseJSON?.error ?? x.responseJSON?.message ?? 'Error inesperado.';

    function cargarEstado() {
        $.getJSON(R.estado, function (r) {
            $('#tb-estado tbody').html(r.indicadores.map(i => `<tr>
                <td>${esc(i.nombre)}${i.cuadro ? ` <span class="text-muted-2 small">· c${i.cuadro}</span>` : ''}</td>
                <td><span class="source-tag">${esc(i.fuente)}</span></td>
                <td class="num ${+i.total ? '' : 'var-pos'}">${nf.format(i.total)}</td>
                <td class="num">${i.desde ? esc(i.desde.slice(0, 7)) : '—'}</td>
                <td class="num">${i.hasta ? esc(i.hasta.slice(0, 7)) : '—'}</td></tr>`).join(''));
            $('#tb-log tbody').html(r.sincronizaciones.map(s => `<tr>
                <td class="small">${esc(new Date(s.created_at).toLocaleString('es-CR'))}</td>
                <td><span class="${s.exito ? '' : 'var-pos'}">${esc(s.detalle)}</span>${s.mensaje ? `<div class="small text-muted-2">${esc(s.mensaje)}</div>` : ''}</td>
                <td class="num">${nf.format(s.registros)}</td></tr>`).join('') || '<tr><td colspan="3" class="text-muted-2">Sin registros</td></tr>');
        });
    }

    $('#btn-sync, #btn-sync-full').on('click', function () {
        const $b = $('#btn-sync, #btn-sync-full').prop('disabled', true);
        $('#msg-sync').html('<span class="text-2"><span class="spinner-border spinner-border-sm me-1"></span> Consultando al BCCR…</span>');
        $.post(R.sincronizar, { completo: $(this).data('completo') })
            .done((r) => { msg('#msg-sync', true, r.message); cargarEstado(); })
            .fail((x) => msg('#msg-sync', false, errorDe(x)))
            .always(() => $b.prop('disabled', false));
    });

    $('#form-cba').on('submit', function (e) {
        e.preventDefault();
        const $b = $(this).find('button').prop('disabled', true);
        $('#msg-cba').html('<span class="text-2"><span class="spinner-border spinner-border-sm me-1"></span> Leyendo el Excel…</span>');
        $.ajax({ url: R.canasta, method: 'POST', data: new FormData(this), processData: false, contentType: false })
            .done((r) => { msg('#msg-cba', true, r.message); cargarEstado(); })
            .fail((x) => msg('#msg-cba', false, errorDe(x)))
            .always(() => $b.prop('disabled', false));
    });

    cargarEstado();
});
</script>
@endif
</body>
</html>
