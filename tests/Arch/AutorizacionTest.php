<?php

// ADR-026: la autorización usa spatie/permission + App\Support\Alcance en los Services.
// No se usan Policies ni Gates. Única excepción: el Gate `viewHorizon` del panel de Horizon.
arch('no existen clases Policy')
    ->expect('App')
    ->not->toHaveSuffix('Policy');

arch('solo Horizon define un Gate')
    ->expect('Illuminate\Support\Facades\Gate')
    ->not->toBeUsed()
    ->ignoring('App\Providers\HorizonServiceProvider');
