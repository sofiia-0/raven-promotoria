<?php

return [
    // Propuestas iniciales: ninguna opción implica aprobación de Coordinadora.
    'visit_objective' => [
        ['code' => 'commercial_followup', 'name' => 'Seguimiento comercial'],
        ['code' => 'information_review', 'name' => 'Verificación de información'],
        ['code' => 'other', 'name' => 'Otro (especificar)', 'is_other' => true],
    ],
    'request_alta' => [
        ['code' => 'new_establishment', 'name' => 'Nuevo establecimiento identificado'],
        ['code' => 'new_branch', 'name' => 'Apertura de nueva sucursal'],
        ['code' => 'coverage_extension', 'name' => 'Ampliación de cobertura'],
        ['code' => 'other', 'name' => 'Otro (especificar)', 'is_other' => true],
    ],
    'request_baja' => [
        ['code' => 'establishment_closure', 'name' => 'Cierre del establecimiento'],
        ['code' => 'business_inactivity', 'name' => 'Establecimiento sin actividad comercial'],
        ['code' => 'coverage_change', 'name' => 'Retiro de cobertura'],
        ['code' => 'other', 'name' => 'Otro (especificar)', 'is_other' => true],
    ],
];
