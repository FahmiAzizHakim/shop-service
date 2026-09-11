<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Establishes who is calling, from the token alone.
 *
 * This service holds no users table -- the gateway owns it -- so identity
 * cannot be looked up here, and does not need to be. The gateway signs the
 * claims a service needs into the token (see its User::getJWTCustomClaims),
 * and the same JWT_SECRET is configured on both sides, so verifying the
 * signature here is enough to trust them: a claim cannot be changed without
 * invalidating the token, and forging one would mean holding the secret.
 *
 * Deliberately NOT auth('api')->user(): that resolves a User model out of a
 * users table this service does not have, and would fail on tymon's `prv`
 * check even if it did. The payload is the whole point -- one HMAC over a few
 * hundred bytes, no query, no round trip back to the gateway.
 *
 * Claims land on the request's attribute bag rather than in a container
 * binding, because the request is what they describe and what gets passed
 * around. The helpers in app/Helpers/helpers.php read them from there, so a
 * controller scoping a query never touches this class.
 *
 * What this does not check is the blacklist. Tymon keeps blacklisted tokens
 * in the cache, and each service has its own database behind CACHE_STORE, so
 * a token invalidated at logout is only known to the gateway. That is the
 * right place for it: the gateway runs auth:api on the way in and is the only
 * caller a service accepts, so a logged-out token never gets this far. A
 * service exposed directly would honour a token until it expired -- which is
 * one more reason the services stay private.
 */
class VerifyJwt
{
    public function handle(Request $request, Closure $next)
    {
        // Throws when the header is absent, the signature does not match, or
        // the token has expired. Each maps to its own 401 in
        // ApiExceptionHandler, so the frontend can tell "sign in again" from
        // "something is wrong with this token".
        $payload = JWTAuth::parseToken()->getPayload();

        $request->attributes->set('jwt_verified', true);
        $request->attributes->set('user_id', $payload->get('sub'));
        $request->attributes->set('user_email', $payload->get('email'));
        $request->attributes->set('user_name', $payload->get('name'));
        $request->attributes->set('roles_code', $payload->get('roles_code'));
        $request->attributes->set('website_id', $payload->get('website_id'));

        return $next($request);
    }
}
