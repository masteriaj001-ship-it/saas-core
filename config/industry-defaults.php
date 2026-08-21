<?php

declare(strict_types=1);

return [
    'industries' => [
        'general' => [
            'label' => 'Operaciones tipo Taller',
            'categories' => ['Servicios', 'Repuestos', 'Productos', 'Materiales'],
            'items' => [
                ['name' => 'Filtro de aceite',        'item_type' => 'product', 'price' => 150,  'sku' => 'REP-001'],
                ['name' => 'Revisión general',        'item_type' => 'service', 'price' => 500,  'sku' => 'SER-001'],
                ['name' => 'Llanta 175/70R13',        'item_type' => 'product', 'price' => 1200, 'sku' => 'REP-002'],
                ['name' => 'Lubricante sintético',    'item_type' => 'product', 'price' => 250,  'sku' => 'LUB-001'],
            ],
            'assets' => [
                ['name' => 'Equipo de Diagnóstico Escáner', 'asset_type' => 'equipment', 'status' => 'active'],
                ['name' => 'Elevador Hidráulico 2T',       'asset_type' => 'equipment', 'status' => 'active'],
            ],
            'service_catalogs' => [
                [
                    'name' => 'Cambio de aceite y filtro',
                    'description' => 'Cambio de aceite mineral, sintético o semisintético + filtro',
                    'base_price' => 45000,
                    'estimated_minutes' => 30,
                ],
                [
                    'name' => 'Revisión de frenos',
                    'description' => 'Inspección de pastillas, discos y líquido de frenos',
                    'base_price' => 35000,
                    'estimated_minutes' => 45,
                ],
                [
                    'name' => 'Diagnóstico electrónico',
                    'description' => 'Lectura de códigos de falla con escáner OBD2',
                    'base_price' => 25000,
                    'estimated_minutes' => 20,
                ],
            ],
        ],
    ],
];
