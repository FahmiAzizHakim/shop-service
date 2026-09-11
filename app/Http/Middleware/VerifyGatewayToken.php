<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Refuses anything that did not come through the gateway.
 *
 * This runs on every API request, public ones included, and that is the whole
 * reason it exists. The JWT covers the admin half; the storefront half -- the
 * catalogue, the basket, the region lists -- is read by visitors who have not
 * signed in and never will, so those requests carry no token and, until now,
 * nothing at all distinguished one arriving from the gateway from one typed
 * into a browser. X-Gateway-Token is what distinguishes them.
 *
 * It is a second layer, not the first. The services are bound to loopback and
 * should be unreachable from outside the host; this is what still holds if
 * that is ever misconfigured -- a port opened, a container published, a proxy
 * rule too wide. Neither layer is asked to be sufficient alone.
 *
 * An empty configured token fails closed. The alternative -- treating "no
 * secret set" as "no check needed" -- would mean a service silently accepting
 * the world the moment an env var went missing, which is exactly the accident
 * this class is here to survive.
 *
 * hash_equals, not ===: the comparison is against a secret, and a timing
 * difference on an early-mismatching byte is a way to learn it one byte at a
 * time. It costs nothing to compare in constant time.
 */
class VerifyGatewayToken
{
    /** Header the gateway sets on every forward. */
    public const HEADER = 'X-Gateway-Token';

    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('gateway.token');
        $provided = (string) $request->header(self::HEADER, '');

        if ($expected === '') {
            // Logged rather than described to the caller: which of the two
            // sides is misconfigured is not a stranger's business.
            Log::error('gateway token is not configured; refusing all requests');

            return $this->refuse();
        }

        if (!hash_equals($expected, $provided)) {
            return $this->refuse();
        }

        return $next($request);
    }

    /**
     * 403, not 401.
     *
     * 401 invites the caller to try again with credentials, and there are none
     * to offer: this is not a user who needs to sign in, it is a request that
     * should not have reached this service at all. The message says nothing
     * about what was missing or wrong.
     */
    protected function refuse()
    {
        return response()->json(['message' => 'Forbidden'], 403);
    }
}
