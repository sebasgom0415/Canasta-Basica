<?php

/*
|--------------------------------------------------------------------------
| Catálogo de indicadores
|--------------------------------------------------------------------------
| Solo se guardan niveles (índices o montos); las variaciones mensual,
| interanual y acumulada se calculan en App\Services\InflacionService.
|
| grupo: ipc | division | componente | subyacente | salario | expectativa
|        | tipo_cambio | canasta
*/

return [
    'base'  => env('BCCR_API_BASE', 'https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API'),
    'token' => env('BCCR_API_TOKEN'),

    // Fecha desde la que se sincroniza el historial completo
    'desde' => '2015-01-01',

    // Meta de inflación del BCCR: 3 % ± 1 p.p.
    'meta' => ['centro' => 3.0, 'min' => 2.0, 'max' => 4.0],

    'indicadores' => [
        // Cuadro 51 · IPC general (dic 2020 = 100)
        ['codigo' => '89635', 'cuadro' => 51,  'grupo' => 'ipc',         'nombre' => 'IPC general', 'corto' => 'IPC'],

        // Cuadro 52 · IPC por divisiones de consumo
        ['codigo' => '89668', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Alimentos y bebidas no alcohólicas',              'corto' => 'Alimentos',     'icono' => 'basket'],
        ['codigo' => '89671', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Bebidas alcohólicas y tabaco',                    'corto' => 'Alcohol y tabaco', 'icono' => 'cup-straw'],
        ['codigo' => '89674', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Prendas de vestir y calzado',                     'corto' => 'Vestido',       'icono' => 'bag'],
        ['codigo' => '89677', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Alquiler y servicios de la vivienda',             'corto' => 'Vivienda',      'icono' => 'house'],
        ['codigo' => '89680', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Artículos para la vivienda y servicio doméstico', 'corto' => 'Hogar',         'icono' => 'lamp'],
        ['codigo' => '89683', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Salud',                                           'corto' => 'Salud',         'icono' => 'heart-pulse'],
        ['codigo' => '89686', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Transporte',                                      'corto' => 'Transporte',    'icono' => 'bus-front'],
        ['codigo' => '89689', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Información y comunicación',                      'corto' => 'Comunicación',  'icono' => 'phone'],
        ['codigo' => '89692', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Recreación, deporte y cultura',                   'corto' => 'Recreación',    'icono' => 'controller'],
        ['codigo' => '89695', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Educación',                                       'corto' => 'Educación',     'icono' => 'mortarboard'],
        ['codigo' => '89698', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Comidas fuera del hogar y alojamiento',           'corto' => 'Restaurantes',  'icono' => 'egg-fried'],
        ['codigo' => '89721', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Servicios financieros y seguros',                 'corto' => 'Financieros',   'icono' => 'bank'],
        ['codigo' => '89701', 'cuadro' => 52,  'grupo' => 'division', 'nombre' => 'Bienes y servicios diversos',                     'corto' => 'Diversos',      'icono' => 'three-dots'],

        // Cuadro 53 · Componentes
        ['codigo' => '89640', 'cuadro' => 53,  'grupo' => 'componente', 'nombre' => 'Regulados',    'corto' => 'Regulados'],
        ['codigo' => '89645', 'cuadro' => 53,  'grupo' => 'componente', 'nombre' => 'No regulados', 'corto' => 'No regulados'],
        ['codigo' => '89650', 'cuadro' => 53,  'grupo' => 'componente', 'nombre' => 'Bienes',       'corto' => 'Bienes'],
        ['codigo' => '89653', 'cuadro' => 53,  'grupo' => 'componente', 'nombre' => 'Servicios',    'corto' => 'Servicios'],

        // Cuadro 680 · Inflación subyacente (promedio de indicadores)
        ['codigo' => '90267', 'cuadro' => 680, 'grupo' => 'subyacente', 'nombre' => 'Inflación subyacente (promedio)', 'corto' => 'Subyacente'],

        // Cuadro 792 · Índice de salarios mínimos reales (2022 = 100)
        ['codigo' => '90930', 'cuadro' => 792, 'grupo' => 'salario', 'nombre' => 'Índice de salarios mínimos reales', 'corto' => 'Salario mínimo real'],

        // Cuadro 793 · Expectativas de mercado (ya viene en %)
        ['codigo' => '84990', 'cuadro' => 793, 'grupo' => 'expectativa', 'nombre' => 'Expectativa de inflación a 12 meses', 'corto' => 'Expectativa 12m', 'unidad' => '%'],

        // Cuadro 1 · Tipo de cambio de venta (diario)
        ['codigo' => '318', 'cuadro' => 1, 'grupo' => 'tipo_cambio', 'nombre' => 'Tipo de cambio de venta', 'corto' => 'TC venta', 'unidad' => '₡', 'frecuencia' => 'diaria'],

        // INEC · Canasta Básica Alimentaria 2011, costo per cápita mensual (Excel de series históricas)
        // Los subgrupos de alimentos (grupo 'subgrupo') los crea el importador.
        ['codigo' => 'CBA_NAC', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'canasta', 'nombre' => 'Costo CBA per cápita · total país', 'corto' => 'CBA nacional', 'unidad' => '₡'],
        ['codigo' => 'CBA_URB', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'canasta', 'nombre' => 'Costo CBA per cápita · zona urbana', 'corto' => 'CBA urbana',  'unidad' => '₡'],
        ['codigo' => 'CBA_RUR', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'canasta', 'nombre' => 'Costo CBA per cápita · zona rural',  'corto' => 'CBA rural',   'unidad' => '₡'],

        // INEC · Línea de pobreza total (la de pobreza extrema es el costo de la CBA)
        ['codigo' => 'LP_NAC', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'pobreza', 'nombre' => 'Línea de pobreza per cápita · total país', 'corto' => 'Línea de pobreza',        'unidad' => '₡'],
        ['codigo' => 'LP_URB', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'pobreza', 'nombre' => 'Línea de pobreza per cápita · zona urbana', 'corto' => 'Línea de pobreza urbana', 'unidad' => '₡'],
        ['codigo' => 'LP_RUR', 'cuadro' => null, 'fuente' => 'INEC', 'grupo' => 'pobreza', 'nombre' => 'Línea de pobreza per cápita · zona rural',  'corto' => 'Línea de pobreza rural',  'unidad' => '₡'],
    ],

    // Íconos (Bootstrap Icons) para los subgrupos de la CBA
    'iconos_subgrupo' => [
        'lacteos' => 'cup', 'carne' => 'fire', 'embutidos' => 'fire', 'pescado' => 'water',
        'leguminosas' => 'flower3', 'hortalizas' => 'flower1', 'frutas' => 'apple', 'tuberculos' => 'tree',
        'cereales' => 'basket2', 'pan' => 'cake2', 'huevo' => 'egg', 'azucar' => 'cup-hot',
        'grasas' => 'droplet', 'bebidas' => 'cup-straw', 'otros' => 'three-dots',
    ],
];
