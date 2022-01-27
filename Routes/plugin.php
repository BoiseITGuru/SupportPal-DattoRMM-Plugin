<?php

$router->get('settings', [
    'can'  => 'view.dattormm_settings',
    'as'   => 'plugin.dattormm.settings',
    'uses' => 'App\Plugins\DattoRMM\Controllers\DattoRMM@getSettingsPage'
]);

$router->post('settings', [
    'can'  => 'update.dattormm_settings',
    'as'   => 'plugin.dattormm.settings.update',
    'uses' => 'App\Plugins\DattoRMM\Controllers\DattoRMM@updateSettings'
]);
