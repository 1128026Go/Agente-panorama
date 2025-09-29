<?php

/*
 |--------------------------------------------------------------------------
 | Configuración de servicios y combos para LAIA
 |--------------------------------------------------------------------------
 |
 | Aquí defines los servicios disponibles, sus nombres y precios de
 | referencia, así como combinaciones de servicios (combos) que
 | ofrecen un precio especial cuando se solicitan juntos.  Estas
 | configuraciones se utilizan en QuoteService y AgentService.
 */

return [
    'services' => [
        'SVC_VEH'      => ['name' => 'Análisis Vehicular',    'price' => 950000],
        'SVC_COLAS'    => ['name' => 'Análisis de Colas',      'price' => 950000],
        'SVC_PED'      => ['name' => 'Análisis Peatonal',      'price' => 650000],
        'SVC_AFORO'    => ['name' => 'Aforo Vehicular',        'price' => 650000],
        'SVC_ANDEN'    => ['name' => 'PMT Cierre de Andén',     'price' => 650000],
        'SVC_DESCARGUE'=> ['name' => 'PMT Descargue Materiales','price' => 650000],
        'SVC_VOLQUETA' => ['name' => 'PMT Entrada/Salida Volquetas','price' => 650000],
    ],

    // Combos que aplican descuentos cuando se contratan juntos.
    'combos' => [
        [
            'services'    => ['SVC_VEH', 'SVC_PED'],
            'total_price' => 1500000,
        ],
    ],

    // Moneda y símbolo utilizados en las cotizaciones
    'currency'        => 'COP',
    'currency_symbol' => '$',

    // Tasa de impuesto (p. ej. IVA).  Se usa para calcular totales.
    'tax_rate'        => 0.19,

    // Mapa opcional que traduce códigos de servicio internos a identificadores de la API de cálculo.
    'api_service_map' => [
        // 'SVC_VEH' => 'VEH',
        // 'SVC_COLAS' => 'COLAS',
    ],
];