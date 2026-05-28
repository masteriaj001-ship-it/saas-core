<?php

declare(strict_types=1);

return [
    'general' => [
        'categories' => ['General', 'Electrónica', 'Hogar', 'Papelería'],
        'items'       => [
            ['name' => 'Producto de ejemplo',     'item_type' => 'product', 'price' => 100, 'sku' => 'PRO-001'],
            ['name' => 'Servicio de ejemplo',     'item_type' => 'service', 'price' => 50,  'sku' => 'SER-001'],
            ['name' => 'Artículo de oficina',     'item_type' => 'product', 'price' => 25,  'sku' => 'PRO-002'],
        ],
    ],
    'mechanic' => [
        'categories' => ['Repuestos', 'Servicios', 'Lubricantes', 'Neumáticos'],
        'items'       => [
            ['name' => 'Filtro de aceite',        'item_type' => 'product', 'price' => 150,  'sku' => 'REP-001'],
            ['name' => 'Revisión general',        'item_type' => 'service', 'price' => 500,  'sku' => 'SER-001'],
            ['name' => 'Llanta 175/70R13',        'item_type' => 'product', 'price' => 1200, 'sku' => 'REP-002'],
        ],
    ],
    'restaurant' => [
        'categories' => ['Bebidas', 'Entradas', 'Platos principales', 'Postres'],
        'items'       => [
            ['name' => 'Agua mineral',            'item_type' => 'product', 'price' => 25,  'sku' => 'BEB-001'],
            ['name' => 'Hamburguesa clásica',     'item_type' => 'product', 'price' => 120, 'sku' => 'PLA-001'],
            ['name' => 'Cerveza artesanal',       'item_type' => 'product', 'price' => 65,  'sku' => 'BEB-002'],
        ],
    ],
    'construction' => [
        'categories' => ['Materiales', 'Herramientas', 'Equipos', 'Seguridad'],
        'items'       => [
            ['name' => 'Cemento 50kg',            'item_type' => 'product', 'price' => 250, 'sku' => 'MAT-001'],
            ['name' => 'Martillo demoledor',      'item_type' => 'product', 'price' => 3500,'sku' => 'HER-001'],
            ['name' => 'Casco de seguridad',      'item_type' => 'product', 'price' => 180, 'sku' => 'SEG-001'],
        ],
    ],
    'clinic' => [
        'categories' => ['Medicamentos', 'Consultas', 'Exámenes', 'Procedimientos'],
        'items'       => [
            ['name' => 'Paracetamol 500mg',       'item_type' => 'product', 'price' => 80,  'sku' => 'MED-001'],
            ['name' => 'Consulta general',        'item_type' => 'service', 'price' => 600, 'sku' => 'SER-001'],
            ['name' => 'Radiografía',             'item_type' => 'service', 'price' => 400, 'sku' => 'SER-002'],
        ],
    ],
];
