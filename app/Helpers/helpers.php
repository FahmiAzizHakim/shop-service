<?php

/*
|--------------------------------------------------------------------------
| Request context
|--------------------------------------------------------------------------
|
| This service holds no users table -- identity arrives with the request.
| VerifyJwt verifies the gateway's token and writes its claims onto the
| request attribute bag; these helpers read them from there. A verified token
| is the whole answer -- no query, and no way for a caller to widen its own
| scope, because the header fallback below is skipped once one is present.
|
| That fallback (X-Website-Id, or a website_id field) is what makes an
| unauthenticated endpoint callable from curl or Postman. It is only ever
| consulted when no token was verified, so it cannot override one.
|
| Every service scopes its queries through admin_website_id(), so wiring auth
| later is a change to the middleware alone.
*/

if (!function_exists('acting_website_id')) {
    /**
     * The website this request acts on.
     */
    function acting_website_id()
    {
        $request = request();

        // A verified token settles it: returning its claim even when null is
        // the point, so a request that arrived with one can never fall
        // through to a header it also sent.
        if ($request->attributes->get('jwt_verified')) {
            return $request->attributes->get('website_id');
        }

        return $request->header('X-Website-Id')
            ?? $request->input('website_id');
    }
}

if (!function_exists('admin_website_id')) {
    /**
     * Alias kept so every service keeps scoping through one name.
     */
    function admin_website_id()
    {
        return acting_website_id();
    }
}

if (!function_exists('acting_user_email')) {
    /**
     * Who to stamp on created_by / updated_by.
     */
    function acting_user_email()
    {
        $request = request();

        if ($request->attributes->get('jwt_verified')) {
            return $request->attributes->get('user_email');
        }

        return $request->header('X-User-Email');
    }
}
