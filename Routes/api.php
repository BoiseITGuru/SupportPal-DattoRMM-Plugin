<?php

 $api->version('v1', ['namespace' => 'App\Plugins\DattoRMM\Controllers\Api'], function ($api) {

    $api->post('plugin/dattormm/email-hook', [
        'as'   => 'plugin.dattormm.api.hook.email',
        'uses' => 'EmailController@inboundEmail'
    ]);

    // $api->post('plugin/dattormm/connect', [
    //     'as'   => 'plugin.dattormm.api.hook.email',
    //     'uses' => 'RmmController@dattoRmmConnect'
    // ]);

});
