<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The gateway's shared secret
    |--------------------------------------------------------------------------
    |
    | This service answers the gateway and nothing else. Every request it
    | accepts must carry X-Gateway-Token matching this value, which the gateway
    | sets on each forward and no browser ever sees.
    |
    | It is not a user credential and does not replace the JWT: the JWT says
    | which user is calling, and on the public routes -- the catalogue, the
    | cart, the region lists -- there is no user, so there is no token to
    | check. This is what those requests are checked against instead. On the
    | admin routes the two stack: this proves the request came from the
    | gateway, the JWT proves who asked for it.
    |
    | Set it to the same value as the gateway's GATEWAY_TOKEN. Left empty, the
    | middleware refuses every request rather than accepting any -- a missing
    | secret is a broken deployment, and failing closed is the only safe way to
    | read it.
    |
    */

    'token' => env('GATEWAY_TOKEN'),

];
