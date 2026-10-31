<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Canasta Básica CR · Inflación y costo de vida</title>
    <meta name="description" content="Dashboard de inflación y Canasta Básica Alimentaria de Costa Rica con datos del BCCR y el INEC.">
    <script>
        // Aplica el tema guardado antes de pintar para evitar parpadeo
        try { const t = localStorage.getItem('tema'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}?v={{ filemtime(public_path('assets/css/dashboard.css')) }}">
</head>
<body>

{{-- ═══ Hero ═══ --}}
<header class="hero">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="hero-eyebrow"><i class="bi bi-basket2-fill me-1"></i> Costa Rica · Costo de vida</div>
                <h1 class="hero-title">Inflación y Canasta Básica</h1>
                <p class="hero-sub mb-3">
                    Cruza el Índice de Precios al Consumidor del <strong>BCCR</strong> con el costo de la Canasta Básica
                    Alimentaria del <strong>INEC</strong>, el tipo de cambio y el salario mínimo real para ver qué está
                    subiendo, qué está bajando y cuánto rinde el colón.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="hero-chip"><i class="bi bi-calendar3"></i> <span id="hero-mes">Cargando…</span></span>
                    <span class="hero-chip"><i class="bi bi-database"></i> BCCR · INEC</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-hero btn-guia" type="button" data-bs-toggle="modal" data-bs-target="#modal-guia">
                    <i class="bi bi-emoji-smile me-1"></i><span>Guía fácil</span>
                </button>
                <button class="btn btn-hero" id="btn-tema" type="button" aria-label="Cambiar tema claro/oscuro">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <a class="btn btn-hero" href="{{ route('admin.index') }}" aria-label="Administración"><i class="bi bi-gear"></i></a>
            </div>
        </div>
    </div>
</header>

{{-- ═══ KPIs ═══ --}}
<div class="container kpis">
    <div class="row g-3" id="kpis">
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-graph-up-arrow"></i> Inflación interanual
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es la inflación interanual?" data-bs-title="Inflación interanual"
                        data-bs-content="¿Cuánto más caras están las cosas que hace un año? Si dice +3 %, el juguete que costaba ₡100 ahora cuesta ₡103. Si dice un número negativo, las cosas se pusieron un poquito más baratas.">?</button></div>
                <div class="kpi-value" id="k-inter">—</div>
                <div class="kpi-foot"><span id="k-meta" class="badge-meta">—</span></div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-calendar-month"></i> Variación mensual
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es la variación mensual?" data-bs-title="Variación mensual"
                        data-bs-content="Lo mismo, pero comparando con el mes pasado en vez del año pasado. «Acumulada» es cuánto han subido los precios desde que empezó este año.">?</button></div>
                <div class="kpi-value" id="k-mensual">—</div>
                <div class="kpi-foot">Acumulada en el año: <span id="k-acum" class="fw-semibold">—</span></div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-bullseye"></i> Subyacente
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es la inflación subyacente?" data-bs-title="Inflación subyacente"
                        data-bs-content="Algunos precios suben y bajan como montaña rusa (la gasolina, las verduras). La subyacente los deja de lado para ver si las cosas, en general, se están encareciendo de verdad. La «expectativa» es lo que los expertos creen que pasará en un año.">?</button></div>
                <div class="kpi-value" id="k-sub">—</div>
                <div class="kpi-foot">Expectativa 12 m: <span id="k-exp" class="fw-semibold">—</span></div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-basket"></i> Canasta básica
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es la canasta básica?" data-bs-title="Canasta básica"
                        data-bs-content="Imagina una lonchera con toda la comida que una persona necesita para estar sana un mes entero: arroz, frijoles, leche, huevos, frutas, carne… Este número es lo que cuesta llenarla.">?</button></div>
                <div class="kpi-value" id="k-cba">—</div>
                <div class="kpi-foot" id="k-cba-foot">per cápita al mes</div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-house-heart"></i> Línea de pobreza
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es la línea de pobreza?" data-bs-title="Línea de pobreza"
                        data-bs-content="Para vivir no solo hace falta comida: también casa, ropa, bus, luz y escuela. Si una persona tiene menos que esta plata al mes para todo eso, se dice que vive en pobreza.">?</button></div>
                <div class="kpi-value" id="k-lp">—</div>
                <div class="kpi-foot" id="k-lp-foot">per cápita al mes</div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="kpi">
                <div class="kpi-label"><i class="bi bi-wallet2"></i> Salario mínimo real
                    <button type="button" class="kpi-help" data-bs-toggle="popover" aria-label="¿Qué es el salario mínimo real?" data-bs-title="Salario mínimo real"
                        data-bs-content="El salario mínimo es lo menos que se le puede pagar a alguien por trabajar. «Real» quiere decir que ya le restamos lo que subieron los precios. Si es positivo, con ese sueldo se compran más cosas que hace un año.">?</button></div>
                <div class="kpi-value" id="k-sal">—</div>
                <div class="kpi-foot" id="k-sal-foot">variación interanual</div>
            </div>
        </div>
    </div>
    <button type="button" class="ayuda-btn mt-3" data-bs-toggle="collapse" data-bs-target="#ayuda-kpis" aria-expanded="false" aria-controls="ayuda-kpis">
        <i class="bi bi-emoji-smile"></i> ¿Qué me dicen estos números?
    </button>
    <div class="mt-3">
        <x-ayuda id="ayuda-kpis" titulo="Los números de hoy, explicados fácil">
            <div class="ayuda-grid kpi-exp" id="kpi-exp"><p>Cargando…</p></div>
            <p class="ayuda-tip"><i class="bi bi-arrow-repeat"></i> Estas explicaciones se escriben solas con los datos más nuevos, así que cambian cada mes.</p>
        </x-ayuda>
    </div>
</div>

{{-- ═══ Sub-navegación ═══ --}}
<nav class="subnav mt-4" id="subnav" aria-label="Secciones">
    <div class="container">
        <ul class="nav">
            <li class="nav-item"><a class="nav-link active" href="#inflacion">Inflación</a></li>
            <li class="nav-item"><a class="nav-link" href="#categorias">Por categoría</a></li>
            <li class="nav-item"><a class="nav-link" href="#canasta">Canasta básica</a></li>
            <li class="nav-item"><a class="nav-link" href="#poder">Poder adquisitivo</a></li>
        </ul>
    </div>
</nav>

<main class="container pb-2">

    {{-- ═══ 1. Inflación general ═══ --}}
    <section class="section" id="inflacion">
        <div class="section-head">
            <div>
                <div class="section-kicker">01 · Inflación general <span class="source-tag ms-1">BCCR</span></div>
                <h2 class="section-title">¿Está la inflación dentro de la meta?</h2>
                <p class="section-desc">Variación interanual del IPC frente a la inflación subyacente, las expectativas del mercado y el rango meta del Banco Central (3 % ± 1 p.p.).</p>
                <button type="button" class="ayuda-btn" data-bs-toggle="collapse" data-bs-target="#ayuda-1" aria-expanded="false" aria-controls="ayuda-1">
                    <i class="bi bi-emoji-smile"></i> ¿Qué significa esto?
                </button>
            </div>
            <div class="filter-row">
                <div class="seg" role="group" aria-label="Rango de tiempo" id="rango-ipc">
                    <button data-meses="24">2 años</button>
                    <button data-meses="60" class="active">5 años</button>
                    <button data-meses="0">Todo</button>
                </div>
            </div>
        </div>
        <x-ayuda id="ayuda-1" titulo="La inflación, explicada fácil">
            <div class="ayuda-grid">
                <div>
                    <h4><i class="bi bi-cup-hot"></i> ¿Qué es la inflación?</h4>
                    <p>Imagina que hoy un helado cuesta <strong>₡1 000</strong>. Si dentro de un año el mismo helado cuesta <strong>₡1 030</strong>, subió un <strong>3 %</strong>. Eso es la inflación: cuando las cosas se van poniendo más caras poquito a poquito.</p>
                </div>
                <div>
                    <h4><i class="bi bi-thermometer-half"></i> La franja verde</h4>
                    <p>El Banco Central quiere que los precios suban <strong>entre 2 % y 4 %</strong> al año, como un termómetro: ni frío ni fiebre. Si la línea azul está <strong>arriba</strong> de la franja, las cosas se encarecen muy rápido. Si está <strong>abajo</strong>, suben muy despacio o hasta bajan.</p>
                </div>
                <div>
                    <h4><i class="bi bi-bezier2"></i> Las tres líneas</h4>
                    <p><span class="pinta s1"></span><strong>Azul:</strong> cuánto subieron todos los precios.<br>
                       <span class="pinta s2"></span><strong>Naranja:</strong> lo mismo, pero sin los precios que saltan mucho (la «subyacente»).<br>
                       <span class="pinta s3"></span><strong>Verde punteada:</strong> lo que los expertos <em>creen</em> que va a pasar.</p>
                </div>
                <div>
                    <h4><i class="bi bi-signpost-split"></i> «¿Qué la mueve?»</h4>
                    <p><strong>Regulados</strong> son precios que decide el gobierno: la luz, el agua, el bus y la gasolina. <strong>No regulados</strong> los ponen las tiendas. <strong>Bienes</strong> son cosas que se pueden tocar (un pan); <strong>servicios</strong> son cosas que alguien hace por vos (un corte de pelo).</p>
                </div>
            </div>
            <p class="ayuda-tip"><i class="bi bi-hand-index"></i> Pasá el dedo o el mouse sobre la gráfica para ver el número de cada mes. Con «2 años / 5 años / Todo» cambiás cuánto tiempo ves.</p>
        </x-ayuda>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="panel" id="p-ipc">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Inflación interanual</h3>
                            <p class="panel-sub">Porcentaje · IPC base diciembre 2020</p>
                        </div>
                        <button class="btn-ghost" data-tabla="ipc"><i class="bi bi-table"></i> Ver datos</button>
                    </div>
                    <div class="legend" id="lg-ipc"></div>
                    <div class="chart-box lg"><canvas id="ch-ipc" role="img" aria-label="Inflación interanual del IPC, subyacente y expectativas"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="panel" id="p-comp">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">¿Qué la mueve?</h3>
                            <p class="panel-sub">Interanual por componente · %</p>
                        </div>
                        <button class="btn-ghost" data-tabla="comp"><i class="bi bi-table"></i></button>
                    </div>
                    <div class="seg mb-2" role="group" aria-label="Componentes" id="seg-comp">
                        <button class="active" data-par="reg">Regulados</button>
                        <button data-par="bys">Bienes / servicios</button>
                    </div>
                    <div class="legend" id="lg-comp"></div>
                    <div class="chart-box sm"><canvas id="ch-comp" role="img" aria-label="Inflación por componente"></canvas></div>
                    <div class="insight mt-3" id="ins-comp"><i class="bi bi-lightbulb"></i><span>—</span></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ 2. Por categoría ═══ --}}
    <section class="section" id="categorias">
        <div class="section-head">
            <div>
                <div class="section-kicker">02 · Variación por categoría <span class="source-tag ms-1">BCCR</span></div>
                <h2 class="section-title">¿Qué se encareció y qué se abarató?</h2>
                <p class="section-desc">Las 13 divisiones de consumo del IPC. Rojo: precios al alza. Azul: precios a la baja frente al mismo mes del año anterior.</p>
                <button type="button" class="ayuda-btn" data-bs-toggle="collapse" data-bs-target="#ayuda-2" aria-expanded="false" aria-controls="ayuda-2">
                    <i class="bi bi-emoji-smile"></i> ¿Qué significa esto?
                </button>
            </div>
            <div class="filter-row">
                <label for="sel-mes-div" class="small text-2">Mes</label>
                <select class="form-select sel" id="sel-mes-div"></select>
            </div>
        </div>
        <x-ayuda id="ayuda-2" titulo="Las cajitas de compras, explicadas fácil">
            <div class="ayuda-grid">
                <div>
                    <h4><i class="bi bi-boxes"></i> 13 cajitas</h4>
                    <p>Todo lo que compra una familia se separa en <strong>13 cajitas</strong>: comida, ropa, casa, transporte, salud, escuela… Así podemos ver <em>cuál</em> cajita se puso más cara y cuál más barata.</p>
                </div>
                <div>
                    <h4><i class="bi bi-palette"></i> Rojo y azul</h4>
                    <p><span class="pinta pos"></span><strong>Rojo</strong> = esa cajita cuesta <strong>más</strong> que hace un año.<br>
                       <span class="pinta neg"></span><strong>Azul</strong> = cuesta <strong>menos</strong>.<br>
                       Mientras más larga la barra, más cambió. La rayita punteada es el promedio de todas las cajitas juntas.</p>
                </div>
                <div>
                    <h4><i class="bi bi-grid-3x3"></i> El mapa de calor</h4>
                    <p>Es como un calendario de colores: cada cuadrito es una cajita en un mes. <strong>Más rojo</strong> = más caro, <strong>más azul</strong> = más barato, <strong>gris</strong> = casi igual. Sirve para ver de un vistazo si algo lleva muchos meses subiendo.</p>
                </div>
                <div>
                    <h4><i class="bi bi-coin"></i> «Base 100»</h4>
                    <p>En «Evolución comparada» hacemos como si en el mes base todo costara <strong>100 monedas</strong>. Si hoy la línea está en <strong>112</strong>, eso que costaba 100 monedas ahora cuesta 112. Tocá los botones con nombres para elegir qué cajitas comparar.</p>
                </div>
            </div>
            <p class="ayuda-tip"><i class="bi bi-hand-index"></i> Cambiá el «Mes» arriba a la derecha para ver cómo estaban las cajitas en otro momento.</p>
        </x-ayuda>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="panel col-flex" id="p-div">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Ranking interanual</h3>
                            <p class="panel-sub" id="sub-div">—</p>
                        </div>
                    </div>
                    <div class="chart-fill"><div class="chart-abs"><canvas id="ch-div" role="img" aria-label="Variación interanual por división"></canvas></div></div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="panel" id="p-tabla-div">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Detalle por división</h3>
                            <p class="panel-sub">Variaciones calculadas a partir del índice de cada división</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-clean" id="tb-div">
                            <thead><tr><th>División</th><th class="num">Mensual</th><th class="num">Interanual</th><th class="num">Acumulada</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="panel" id="p-heat">
                    <div class="panel-head flex-wrap">
                        <div>
                            <h3 class="panel-title">Mapa de calor · últimos 12 meses</h3>
                            <p class="panel-sub">Variación interanual (%) de cada división, mes a mes</p>
                        </div>
                        <div class="heat-scale"><span>Baja</span><span class="ramp" id="heat-ramp"></span><span>Sube</span></div>
                    </div>
                    <div class="heat-wrap"><table class="heat" id="heat"></table></div>
                </div>
            </div>
            <div class="col-12">
                <div class="panel" id="p-evo">
                    <div class="panel-head flex-wrap">
                        <div>
                            <h3 class="panel-title">Evolución comparada</h3>
                            <p class="panel-sub">Elija hasta 4 divisiones para compararlas con el IPC general</p>
                        </div>
                        <div class="filter-row">
                            <div class="seg" role="group" aria-label="Modo" id="seg-evo">
                                <button class="active" data-modo="indice">Índice (base 100)</button>
                                <button data-modo="interanual">Interanual %</button>
                            </div>
                            <select class="form-select sel" id="sel-base" aria-label="Mes base"></select>
                            <button class="btn-ghost" data-tabla="evo"><i class="bi bi-table"></i> Ver datos</button>
                        </div>
                    </div>
                    <div class="chips mb-3" id="chips-div"></div>
                    <div class="legend" id="lg-evo"></div>
                    <div class="chart-box lg"><canvas id="ch-evo" role="img" aria-label="Evolución comparada de divisiones"></canvas></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ 3. Canasta básica ═══ --}}
    <section class="section" id="canasta">
        <div class="section-head">
            <div>
                <div class="section-kicker">03 · Canasta Básica Alimentaria <span class="source-tag ms-1">INEC</span> <span class="source-tag">BCCR</span></div>
                <h2 class="section-title">¿Cuánto cuesta comer en Costa Rica?</h2>
                <p class="section-desc">Costo per cápita mensual de la CBA 2011 del INEC, convertido a dólares con el tipo de cambio promedio de venta del BCCR de cada mes.</p>
                <button type="button" class="ayuda-btn" data-bs-toggle="collapse" data-bs-target="#ayuda-3" aria-expanded="false" aria-controls="ayuda-3">
                    <i class="bi bi-emoji-smile"></i> ¿Qué significa esto?
                </button>
            </div>
            <div class="filter-row">
                <div class="seg" role="group" aria-label="Moneda" id="seg-moneda">
                    <button class="active" data-moneda="crc">₡ Colones</button>
                    <button data-moneda="usd">US$ Dólares</button>
                </div>
            </div>
        </div>
        <x-ayuda id="ayuda-3" titulo="La canasta básica, explicada fácil">
            <div class="ayuda-grid">
                <div>
                    <h4><i class="bi bi-basket"></i> La lonchera del mes</h4>
                    <p>La <strong>canasta básica</strong> es toda la comida que una persona necesita para comer bien durante <strong>un mes</strong>: arroz, frijoles, leche, huevos, pollo, frutas, verduras… El INEC va a ver cuánto cuesta todo eso y lo suma.</p>
                </div>
                <div>
                    <h4><i class="bi bi-buildings"></i> Ciudad y campo</h4>
                    <p><span class="pinta s1"></span><strong>Nacional:</strong> el promedio de todo el país.<br>
                       <span class="pinta s2"></span><strong>Urbana:</strong> en la ciudad, donde la comida suele costar más.<br>
                       <span class="pinta s3"></span><strong>Rural:</strong> en el campo.<br>
                       Con el botón <strong>US$</strong> ves cuántos dólares harían falta.</p>
                </div>
                <div>
                    <h4><i class="bi bi-arrow-left-right"></i> Dos fuentes, una historia</h4>
                    <p>El INEC (que cuenta la lonchera) y el Banco Central (que mide los precios de la comida) miden casi lo mismo, por eso sus líneas van casi pegadas. Si la de la lonchera baja más, es porque los alimentos que lleva se abarataron más.</p>
                </div>
                <div>
                    <h4><i class="bi bi-pie-chart"></i> ¿Qué alimentos la mueven?</h4>
                    <p>Si la lonchera fuera una pizza, cada alimento es un pedazo: el <strong>peso</strong> dice qué tan grande es su pedazo. El <strong>aporte</strong> dice cuánto empujó ese alimento para que la lonchera se pusiera más cara (<span class="var-pos">rojo</span>) o más barata (<span class="var-neg">azul/verde</span>). Si sumás todos los aportes, te da el cambio total.</p>
                </div>
            </div>
            <p class="ayuda-tip"><i class="bi bi-hand-index"></i> Ejemplo: si las hortalizas aportan «+1,65 p.p.», quiere decir que solo por ellas la lonchera subió 1,65 %.</p>
        </x-ayuda>
        <div id="cba-vacio" class="panel empty d-none">
            <i class="bi bi-basket"></i>Aún no se han importado los datos del INEC. Súbalos desde el panel de administración.
        </div>
        <div class="row g-3" id="cba-contenido">
            <div class="col-lg-7">
                <div class="panel" id="p-cba">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Costo de la canasta por zona</h3>
                            <p class="panel-sub" id="sub-cba">Per cápita mensual</p>
                        </div>
                        <button class="btn-ghost" data-tabla="cba"><i class="bi bi-table"></i> Ver datos</button>
                    </div>
                    <div class="legend" id="lg-cba"></div>
                    <div class="chart-box"><canvas id="ch-cba" role="img" aria-label="Costo de la canasta básica por zona"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="panel" id="p-cba-ipc">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Canasta INEC vs. IPC de alimentos</h3>
                            <p class="panel-sub">Variación interanual · %</p>
                        </div>
                        <button class="btn-ghost" data-tabla="cbaipc"><i class="bi bi-table"></i></button>
                    </div>
                    <div class="legend" id="lg-cbaipc"></div>
                    <div class="chart-box sm"><canvas id="ch-cbaipc" role="img" aria-label="Variación interanual de la canasta frente al IPC de alimentos"></canvas></div>
                    <div class="insight mt-3" id="ins-cba"><i class="bi bi-lightbulb"></i><span>—</span></div>
                </div>
            </div>
            <div class="col-12">
                <div class="panel" id="p-sg">
                    <div class="panel-head flex-wrap">
                        <div>
                            <h3 class="panel-title">¿Qué alimentos mueven la canasta?</h3>
                            <p class="panel-sub" id="sub-sg">Contribución de cada subgrupo a la variación interanual de la CBA (puntos porcentuales)</p>
                        </div>
                        <div class="filter-row">
                            <label for="sel-mes-sg" class="small text-2">Mes</label>
                            <select class="form-select sel" id="sel-mes-sg"></select>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-5 d-flex flex-column">
                            <div class="chart-fill"><div class="chart-abs"><canvas id="ch-sg" role="img" aria-label="Contribución por subgrupo de alimentos"></canvas></div></div>
                        </div>
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-clean" id="tb-sg">
                                    <thead><tr><th>Subgrupo</th><th class="num">Costo</th><th>Peso</th><th class="num">Interanual</th><th class="num">Aporte</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ 4. Poder adquisitivo ═══ --}}
    <section class="section" id="poder">
        <div class="section-head">
            <div>
                <div class="section-kicker">04 · Poder adquisitivo <span class="source-tag ms-1">BCCR</span></div>
                <h2 class="section-title">¿Cuánto rinde el colón?</h2>
                <p class="section-desc">Actualice un monto por inflación y vea si el salario mínimo le gana o pierde frente a los precios.</p>
                <button type="button" class="ayuda-btn" data-bs-toggle="collapse" data-bs-target="#ayuda-4" aria-expanded="false" aria-controls="ayuda-4">
                    <i class="bi bi-emoji-smile"></i> ¿Qué significa esto?
                </button>
            </div>
        </div>
        <x-ayuda id="ayuda-4" titulo="El poder del colón, explicado fácil">
            <div class="ayuda-grid">
                <div>
                    <h4><i class="bi bi-piggy-bank"></i> La plata se «encoge»</h4>
                    <p>Si guardaste <strong>₡100 000</strong> en una alcancía hace 5 años, hoy siguen siendo ₡100 000… pero como las cosas subieron de precio, ya no te alcanzan para comprar lo mismo. Es como si tu plata se hubiera encogido un poquito.</p>
                </div>
                <div>
                    <h4><i class="bi bi-calculator"></i> La calculadora</h4>
                    <p>Escribí una cantidad y elegí un mes del pasado. Te dice cuánta plata necesitás <strong>hoy</strong> para comprar exactamente lo mismo que comprabas con esa cantidad <strong>en aquel mes</strong>.</p>
                </div>
                <div>
                    <h4><i class="bi bi-wallet2"></i> ¿El sueldo le gana a los precios?</h4>
                    <p>Es una carrera entre el <strong>sueldo mínimo</strong> y los <strong>precios</strong>. Cuando la línea está <strong>arriba del cero</strong>, ganó el sueldo: alcanza para más cosas que hace un año. Cuando está <strong>abajo</strong> (la parte rojita), ganaron los precios y el sueldo alcanza para menos.</p>
                </div>
            </div>
        </x-ayuda>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="panel" id="p-calc">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title"><i class="bi bi-calculator me-1"></i> Calculadora de inflación</h3>
                            <p class="panel-sub">Ajuste por IPC</p>
                        </div>
                    </div>
                    <form id="form-calc" class="row g-2" novalidate>
                        <div class="col-sm-6">
                            <label for="calc-monto" class="form-label small text-2 mb-1">Monto (₡)</label>
                            <input type="number" class="form-control sel" id="calc-monto" value="100000" min="1" step="any" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="calc-desde" class="form-label small text-2 mb-1">En el mes</label>
                            <select class="form-select sel w-100" id="calc-desde"></select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-accent w-100 mt-1">Calcular</button>
                        </div>
                    </form>
                    <div class="calc-result mt-3" id="calc-res" aria-live="polite">
                        <div class="small text-2">Equivale hoy a</div>
                        <div class="calc-big" id="calc-eq">—</div>
                        <div class="small text-2" id="calc-det">—</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="panel" id="p-sal">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Salario mínimo real</h3>
                            <p class="panel-sub">Variación interanual (%) · descontada la inflación</p>
                        </div>
                        <button class="btn-ghost" data-tabla="sal"><i class="bi bi-table"></i> Ver datos</button>
                    </div>
                    <div class="chart-box"><canvas id="ch-sal" role="img" aria-label="Variación interanual del salario mínimo real"></canvas></div>
                    <div class="insight mt-3" id="ins-sal"><i class="bi bi-lightbulb"></i><span>—</span></div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container d-flex flex-wrap justify-content-between gap-3">
        <div>
            <strong class="text-2">Fuentes:</strong> Banco Central de Costa Rica (SDDE · cuadros 1, 51, 52, 53, 680, 792, 793) ·
            INEC, Canasta Básica Alimentaria 2011.
            <br>Las variaciones mensual, interanual y acumulada se calculan a partir de los niveles de cada índice.
        </div>
        <div>Proyecto de portafolio · Laravel + Chart.js</div>
    </div>
</footer>

{{-- Modal: guía fácil --}}
<div class="modal fade" id="modal-guia" tabindex="-1" aria-labelledby="modal-guia-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modal-guia-titulo"><i class="bi bi-emoji-smile me-1"></i> Guía fácil: ¿cómo se lee este tablero?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body guia">
                <p class="guia-intro">Este tablero responde una pregunta muy sencilla: <strong>¿las cosas se están poniendo más caras o más baratas en Costa Rica?</strong> Y también: <strong>¿cuánto cuesta comer?</strong></p>

                <div class="guia-paso">
                    <span class="guia-num">1</span>
                    <div>
                        <h4>¿Quién nos da los datos?</h4>
                        <p><strong>El Banco Central (BCCR)</strong> es como el guardián del dinero del país: revisa todos los meses los precios de muchísimas cosas. <strong>El INEC</strong> es el que cuenta todo en Costa Rica, y calcula cuánto cuesta la comida de una persona. Nosotros juntamos lo de los dos.</p>
                    </div>
                </div>
                <div class="guia-paso">
                    <span class="guia-num">2</span>
                    <div>
                        <h4>¿Qué es eso de la inflación?</h4>
                        <p>Cuando un helado que costaba ₡1 000 pasa a costar ₡1 030, subió <strong>3 %</strong>. La <strong>inflación</strong> es eso mismo, pero mirando <em>todas</em> las cosas que compran las familias a la vez.</p>
                    </div>
                </div>
                <div class="guia-paso">
                    <span class="guia-num">3</span>
                    <div>
                        <h4>Los colores tienen significado</h4>
                        <p><span class="pinta pos"></span><strong>Rojo:</strong> subió de precio (más caro).<br>
                           <span class="pinta neg"></span><strong>Azul:</strong> bajó de precio (más barato).<br>
                           <i class="bi bi-arrow-down-right var-neg"></i> Las <strong>flechitas verdes</strong> son buenas noticias para tu bolsillo; las <strong>rojas</strong>, malas noticias.</p>
                    </div>
                </div>
                <div class="guia-paso">
                    <span class="guia-num">4</span>
                    <div>
                        <h4>Cómo jugar con el tablero</h4>
                        <ul>
                            <li><strong>Pasá el mouse</strong> (o el dedo) sobre una gráfica para ver el número exacto de cada mes.</li>
                            <li>Tocá el <span class="kpi-help d-inline-flex">?</span> de las tarjetas de arriba para una explicación cortita.</li>
                            <li>En cada sección, el botón <span class="ayuda-btn d-inline-flex py-0"><i class="bi bi-emoji-smile"></i> ¿Qué significa esto?</span> te cuenta qué estás viendo.</li>
                            <li><strong>Ver datos</strong> te muestra los números en una tabla, por si preferís leerlos.</li>
                            <li>La <i class="bi bi-moon-stars"></i> luna cambia a modo oscuro.</li>
                        </ul>
                    </div>
                </div>
                <div class="guia-paso">
                    <span class="guia-num">5</span>
                    <div>
                        <h4>Palabras raras, en fácil</h4>
                        <dl class="guia-glosario">
                            <dt>IPC</dt><dd>La «lista de compras» gigante que usa el Banco Central para medir si los precios suben.</dd>
                            <dt>Interanual</dt><dd>Comparado con el mismo mes del año pasado.</dd>
                            <dt>Per cápita</dt><dd>Por cada persona.</dd>
                            <dt>p.p. (puntos porcentuales)</dt><dd>Un pedacito del cambio total. Si la canasta subió 2 % y la carne aportó 1 p.p., la mitad de la subida fue culpa de la carne.</dd>
                            <dt>Tipo de cambio</dt><dd>Cuántos colones cuesta un dólar.</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-accent" data-bs-dismiss="modal">¡Entendido!</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: tabla de datos de cualquier gráfico --}}
<div class="modal fade" id="modal-datos" tabindex="-1" aria-labelledby="modal-datos-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modal-datos-titulo">Datos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><div class="table-responsive"><table class="table table-clean" id="tb-modal"></table></div></div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/chart.umd.min.js') }}"></script>
<script>
    window.ROUTES = {
        resumen:     '{{ route('api.resumen') }}',
        ipc:         '{{ route('api.ipc') }}',
        divisiones:  '{{ route('api.divisiones') }}',
        evolucion:   '{{ route('api.evolucion') }}',
        componentes: '{{ route('api.componentes') }}',
        canasta:     '{{ route('api.canasta') }}',
        subgrupos:   '{{ route('api.subgrupos') }}',
        salario:     '{{ route('api.salario') }}',
        poder:       '{{ route('api.poder') }}',
    };
</script>
<script src="{{ asset('assets/js/dashboard.js') }}?v={{ filemtime(public_path('assets/js/dashboard.js')) }}"></script>
</body>
</html>
