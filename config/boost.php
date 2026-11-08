<?php

// Se fusiona con la configuración del paquete laravel/boost.
// Todas las herramientas de IA usan AGENTS.md; CLAUDE.md solo lo importa (ver docs/12 Desarrollo/Directrices de IA.md).
return [
    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],
];
