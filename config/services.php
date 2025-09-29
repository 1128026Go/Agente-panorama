<?php

return [
    // Configuración para n8n.  Define estas variables en tu .env
    'n8n' => [
        'base_url' => env('N8N_BASE_URL', 'http://localhost:5678'),
        'user'     => env('N8N_BASIC_AUTH_USER'),
        'password' => env('N8N_BASIC_AUTH_PASSWORD'),
    ],

    // Token para validar llamadas internas de n8n hacia Laravel
    'laia_n8n' => [
        'token' => env('LAIA_N8N_TOKEN'),
    ],

    // Configuración para Microsoft Graph
    'graph' => [
        'token' => env('MSFT_GRAPH_TOKEN'),
    ],
];