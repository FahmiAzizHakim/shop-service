<?php

namespace App\Services\Commerce;

use App\Models\Cart;
use App\Repositories\Commerce\CartRepository;
use App\Repositories\Commerce\PackageRepository;
use App\Repositories\Commerce\ProductRepository;
use App\Repositories\Commerce\ServiceRepository;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guest carts for the public sites.
 *
 * There is no login, so a cart belongs to a browser: cart_token is minted on
 * first visit and kept in a year-long cookie (encrypted by Laravel, so the
 * visitor cannot hand themselves someone else's cart by editing it).
 *
 * This class is also the single place that turns a cart key into a price --
 * both the cart and the order use resolveItems(), so a saved cart and the
 * order built from it can never disagree about what something costs.
 */
class CartService
{
    /** Cookie that carries the cart token. */
    public const COOKIE = 'cart_token';

    /** Keep the cart for a year of inactivity. */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /** Guards against a crafted request filling the table. */
    public const MAX_QTY   = 99;
    public const MAX_LINES = 50;

    /**
     * The token minted during this request, if any.
     *
     * A queued cookie is only readable on the NEXT request, so without this a
     * browser's first write would save the cart and then report it back empty
     * -- the read after the write would not find the cart it had just made.
     */
    protected $minted = null;

    protected $carts;
    protected $products;
    protected $packages;
    protected $services;

    public function __construct(
        CartRepository $carts,
        ProductRepository $products,
        PackageRepository $packages,
        ServiceRepository $services
    ) {
        $this->carts    = $carts;
        $this->products = $products;
        $this->packages = $packages;
        $this->services = $services;
    }

    /* =========================================================
     * Browser identity
     * ========================================================= */

    /**
     * The cart token for this browser, or null when it has none yet.
     * A malformed cookie is treated as absent.
     */
    public function token(): ?string
    {
        // A token minted earlier in this same request wins: the cookie
        // carrying it has only been queued, so it is not on the request yet.
        if ($this->minted) {
            return $this->minted;
        }

        $token = request()->cookie(self::COOKIE);

        if (!is_string($token) || !preg_match('/^[A-Za-z0-9-]{16,64}$/', $token)) {
            return null;
        }

        return $token;
    }

    /**
     * The token for this browser, minting (and queueing the cookie) if needed.
     */
    public function tokenOrMint(): string
    {
        $token = $this->token();

        if (!$token) {
            $token = (string) Str::uuid();
        }

        $this->minted = $token;

        // Re-queued on every use so the year of life is counted from the last
        // visit, not from the first one.
        Cookie::queue(Cookie::make(
            self::COOKIE,
            $token,
            self::COOKIE_MINUTES,
            null,
            null,
            null,
            true,      // httpOnly: the token is never needed in JS
            false,
            'lax'
        ));

        return $token;
    }

    /* =========================================================
     * The cart row
     * ========================================================= */

    /**
     * This browser's cart for a website. With $create the cart (and the
     * cookie) are made on the spot; without it, a browser that has never
     * added anything simply has no cart.
     */
    public function currentCart($websiteId, bool $create = false): ?Cart
    {
        if (!$create) {
            $token = $this->token();

            return $token ? $this->carts->findByToken($websiteId, $token) : null;
        }

        $cart = $this->carts->firstOrCreateForToken($websiteId, $this->tokenOrMint());

        return $this->touch($cart);
    }

    /**
     * Record who last used the cart (diagnostics + a handle for pruning).
     */
    protected function touch(Cart $cart): Cart
    {
        return $this->carts->touch($cart, [
            'session_id'       => request()->hasSession() ? request()->session()->getId() : null,
            'ip'               => request()->ip(),
            'user_agent'       => Str::limit((string) request()->userAgent(), 500, ''),
            'last_activity_at' => now(),
        ]);
    }

    /* =========================================================
     * Reading
     * ========================================================= */

    /**
     * The stored lines as the checkout works with them: [['id' => key, 'qty' => n], ...].
     * Nothing is priced here, so this is safe to call on a stale cart.
     */
    public function rawLines($websiteId): array
    {
        $cart = $this->currentCart($websiteId);

        if (!$cart) {
            return [];
        }

        return $this->carts->items($cart)
            ->map(fn ($i) => ['id' => $i->item_key, 'qty' => (int) $i->qty])
            ->all();
    }

    /**
     * The saved cart, priced from the catalogue and ready for the cart script:
     * [['id' => key, 'name' => .., 'price' => gross, 'disc' => unit discount, 'qty' => n], ...]
     *
     * Items that went inactive or were deleted since they were added simply
     * do not come back.
     */
    public function displayLines($websiteId): array
    {
        $rows = $this->resolveItems($websiteId, $this->rawLines($websiteId));

        return array_map(fn ($r) => [
            'id'    => $r['key'],
            'name'  => $r['name'],
            'price' => (int) $r['price'],
            'disc'  => (int) $r['discount'],
            'qty'   => (int) $r['qty'],
        ], $rows);
    }

    /**
     * Number of units in the saved cart (for a header badge, say).
     */
    public function count($websiteId): int
    {
        $cart = $this->currentCart($websiteId);

        return $cart ? $this->carts->sumQty($cart) : 0;
    }

    /* =========================================================
     * Writing
     * ========================================================= */

    /**
     * Replace the cart with $lines ([['id' => key, 'qty' => n], ...]).
     *
     * Lines are resolved against the catalogue first, so unknown or inactive
     * keys are dropped rather than stored. Returns the resolved lines that
     * were actually kept.
     */
    public function save($websiteId, array $lines): array
    {
        $rows = $this->resolveItems($websiteId, $lines);

        // An empty basket means "forget my cart", not "keep the old one".
        if (empty($rows)) {
            $this->clear($websiteId);

            return [];
        }

        $cart = $this->currentCart($websiteId, true);

        DB::transaction(function () use ($cart, $rows) {
            $keep = [];

            foreach ($rows as $r) {
                $this->carts->saveItem($cart, $r['key'], [
                    'item_type'          => $r['kind'],
                    'product_id'         => optional($r['product'])->id,
                    'product_variant_id' => optional($r['variant'])->id,
                    'package_id'         => optional($r['package'])->id,
                    'qty'                => $r['qty'],
                ]);

                $keep[] = $r['key'];
            }

            // Whatever the browser no longer has is gone from the cart.
            $this->carts->deleteItemsExcept($cart, $keep);
        });

        return $rows;
    }

    /**
     * Empty the cart. The row itself is kept (with its token) so the browser
     * keeps the same cart when it comes back.
     */
    public function clear($websiteId): void
    {
        $cart = $this->currentCart($websiteId);

        if (!$cart) {
            return;
        }

        $this->carts->clearItems($cart);
        $this->touch($cart);
    }

    /* =========================================================
     * Catalogue resolution -- the one place cart keys become prices
     * ========================================================= */

    /**
     * Split a cart key into what it points at.
     * 'p3' | 'p3v1' | 'pkg2' | 's4'  ->  ['kind' => .., 'id' => .., 'variant_id' => ..]
     */
    public function parseKey($key): ?array
    {
        $key = (string) $key;

        if (preg_match('/^pkg(\d+)$/', $key, $m)) {
            return ['kind' => 'package', 'id' => (int) $m[1], 'variant_id' => null];
        }

        if (preg_match('/^p(\d+)(?:v(\d+))?$/', $key, $m)) {
            return [
                'kind'       => empty($m[2]) ? 'product' : 'variant',
                'id'         => (int) $m[1],
                'variant_id' => empty($m[2]) ? null : (int) $m[2],
            ];
        }

        if (preg_match('/^s(\d+)$/', $key, $m)) {
            return ['kind' => 'service', 'id' => (int) $m[1], 'variant_id' => null];
        }

        return null;
    }

    /**
     * Price and name raw cart lines against a website's live catalogue.
     * Unknown, inactive or malformed lines are dropped.
     *
     * One row per surviving line:
     *   kind      product|variant|package|service
     *   key       the cart key it came from
     *   qty       1..MAX_QTY
     *   name      display name
     *   price     unit price BEFORE discount
     *   discount  unit discount (packages only; 0 otherwise)
     *   product / variant / package / service   the models behind it, or null
     *
     * @return array<int, array>
     */
    public function resolveItems($websiteId, array $lines): array
    {
        if (empty($lines)) {
            return [];
        }

        $wanted = [];   // key => qty, de-duplicated, capped

        foreach (array_slice($lines, 0, self::MAX_LINES) as $line) {
            $key   = is_array($line) ? ($line['id'] ?? null) : null;
            $parts = $this->parseKey($key);

            if (!$parts) {
                continue;
            }

            $qty = (int) (is_array($line) ? ($line['qty'] ?? 1) : 1);
            $qty = min(self::MAX_QTY, max(1, $qty));

            $wanted[(string) $key] = $qty;
        }

        if (empty($wanted)) {
            return [];
        }

        $parsed = [];
        foreach (array_keys($wanted) as $key) {
            $parsed[$key] = $this->parseKey($key);
        }

        $kinds = array_column($parsed, 'kind');
        $idsOf = fn (array $forKinds) => collect($parsed)
            ->filter(fn ($p) => in_array($p['kind'], $forKinds, true))
            ->pluck('id')->unique()->all();

        // Only load the catalogue slices these keys actually need.
        $products = array_intersect(['product', 'variant'], $kinds)
            ? $this->products->activeByIds($websiteId, $idsOf(['product', 'variant']))
            : collect();

        $packages = in_array('package', $kinds, true)
            ? $this->packages->activeByIds($websiteId, $idsOf(['package']))
            : collect();

        $services = in_array('service', $kinds, true)
            ? $this->services->activeByIds($websiteId, $idsOf(['service']))
            : collect();

        $rows = [];

        foreach ($wanted as $key => $qty) {
            $p = $parsed[$key];

            $row = [
                'kind'     => $p['kind'],
                'key'      => $key,
                'qty'      => $qty,
                'name'     => null,
                'price'    => 0.0,
                'discount' => 0.0,
                'product'  => null,
                'variant'  => null,
                'package'  => null,
                'service'  => null,
            ];

            if ($p['kind'] === 'package') {
                $package = $packages->get($p['id']);
                if (!$package) continue;

                // Gross price here, discount kept separate: the order writes
                // the package at full price and the discount on the header.
                $row['package']  = $package;
                $row['name']     = $package->package_name;
                $row['price']    = (float) $package->package_price;
                $row['discount'] = (float) $package->package_discount;
            } elseif ($p['kind'] === 'service') {
                $service = $services->get($p['id']);
                if (!$service) continue;

                $row['service'] = $service;
                $row['name']    = $service->service_name;
                $row['price']   = 0.0;
            } else {
                $product = $products->get($p['id']);
                if (!$product) continue;

                $row['product'] = $product;
                $row['name']    = $product->products_name;
                $row['price']   = (float) $product->products_price;

                if ($p['kind'] === 'variant') {
                    $variant = $product->variants->firstWhere('id', $p['variant_id']);
                    if (!$variant) continue;

                    $row['variant'] = $variant;
                    $row['name']    = $product->products_name . ' — ' . $variant->variant_name;
                    $row['price']   = !is_null($variant->variant_price)
                        ? (float) $variant->variant_price
                        : (float) $product->products_price;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
