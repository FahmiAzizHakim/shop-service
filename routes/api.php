<?php

use App\Http\Controllers\Api\Admin\BankController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DeliveryPriceController;
use App\Http\Controllers\Api\Admin\OtherChargeController;
use App\Http\Controllers\Api\Admin\PackageController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductHighlightController;
use App\Http\Controllers\Api\Admin\ProductReviewController;
use App\Http\Controllers\Api\Admin\ProductStatsController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| shop-service
|--------------------------------------------------------------------------
|
| The catalogue and everything that happens to it: services, products,
| packages, carts, checkout and orders. The only caller is the API gateway,
| which verifies the JWT and forwards the claims.
|
| Two halves:
|
|   /api/v1/...     public. What a visitor's browser reads and writes while
|                   shopping. No token -- a guest checks out with an address.
|   /api/admin/...  the seller's side. Scoped to one website, taken from the
|                   request context (see app/Helpers/helpers.php) rather than
|                   the URL, so one site's admin cannot address another's rows.
|
| Not here: site identity, theming and editorial content. Those are
| website-service's, and the frontend asks it for them alongside these calls.
*/

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| {website} is a website id. It is a plain column here -- the websites live in
| website-service -- so an unknown id yields empty lists rather than a 404.
*/
Route::prefix('v1')->group(function () {

    // Region lists for the checkout address form. Not website scoped.
    Route::prefix('regions')->group(function () {
        Route::get('/provinces', [RegionController::class, 'provinces']);
        Route::get('/cities/{provinceCode}', [RegionController::class, 'cities']);
        Route::get('/districts/{cityCode}', [RegionController::class, 'districts']);
        Route::get('/subdistricts/{districtCode}', [RegionController::class, 'subdistricts']);
    });

    Route::prefix('/sites/{website}')->whereNumber('website')->group(function () {

        /* ---- Catalogue ---- */

        // Everything the catalogue section renders, in one request.
        Route::get('/catalog', [CatalogController::class, 'index']);
        Route::get('/services', [CatalogController::class, 'services']);
        Route::get('/products', [CatalogController::class, 'products']);
        Route::get('/products/{id}', [CatalogController::class, 'product']);
        // A write, and public: the storefront's product page calls it when it
        // opens. It is its own endpoint rather than a count on the GET above
        // because that page renders from the cached catalogue and never asks
        // for one product -- see CatalogController::view(). Rate limited at
        // the gateway, as the other public writes are.
        Route::post('/products/{id}/view', [CatalogController::class, 'view']);
        // What buyers said, plus the average and the count.
        Route::get('/products/{id}/reviews', [CatalogController::class, 'reviews']);
        Route::get('/packages', [CatalogController::class, 'packages']);
        // The curated rows -- "Best Seller", "New Arrivals" -- each with the
        // products under it. Highlights holding nothing visible are left out.
        Route::get('/highlights', [CatalogController::class, 'highlights']);

        /* ---- Basket ---- */

        // Kept against the cart_token cookie, so no login and no user id.
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart', [CartController::class, 'save']);
        Route::delete('/cart', [CartController::class, 'clear']);

        /* ---- Checkout ---- */

        // The fare the chosen address resolves to, for the running total.
        Route::get('/fare', [RegionController::class, 'fare']);
        // Price the basket without writing anything.
        Route::post('/checkout/confirm', [CheckoutController::class, 'confirm']);
        // Place it. Answers with the receipt token.
        Route::post('/checkout', [CheckoutController::class, 'place']);

        /* ---- Orders ---- */

        // Email + last 4 phone digits. Lightweight check, not a login.
        Route::post('/orders/lookup', [OrderController::class, 'lookup']);
        // Addressed by its unguessable token, which is what keeps it public.
        Route::get('/receipt/{token}', [OrderController::class, 'receipt']);
        Route::post('/receipt/{token}/attachments', [OrderController::class, 'uploadAttachment']);

        /*
         * Reviews, hung off the receipt because the token is what proves the
         * purchase. The GET is what the receipt page reads to decide which
         * product lines still show a Review button; the POST is multipart --
         * the photos ride along -- and is rate limited at the gateway like the
         * other public writes.
         */
        Route::get('/receipt/{token}/reviews', [OrderController::class, 'reviews']);
        Route::post('/receipt/{token}/reviews', [OrderController::class, 'storeReview']);

        /*
        | The QRIS admin fee, settled once Qrisly's nudge is known.
        |
        | The gateway calls this after raising a payment: checkout quoted a flat
        | fee because the nudge did not exist yet, and this writes the difference
        | back as a discount so the order's total matches what will be scanned.
        | Bounded by the provider fee, so it cannot discount the goods.
        */
        Route::post('/receipt/{token}/qris-adjustment', [OrderController::class, 'qrisAdjustment']);

        /*
        | The order is paid, because Qrisly says so.
        |
        | The gateway calls this after a payment check comes back settled. No
        | body: the caller asserts nothing, it only names the order, so holding
        | a receipt token is not a way to mark your own order paid. Idempotent,
        | and it will not walk an order that is already past payment backwards.
        */
        Route::post('/receipt/{token}/qris-paid', [OrderController::class, 'qrisPaid']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
|
| Every route here acts on the caller's own website; none takes a website id.
| Endpoints that carry a file are POST on both create and update -- PHP does
| not parse a multipart body on PUT.
|
| The gateway verifies the token on the way in and forwards it; 'jwt' here
| verifies it again with the shared JWT_SECRET and puts its claims on the
| request, so the scope comes from the token and cannot be asserted by a
| header. No users table is read -- see app/Http/Middleware/VerifyJwt.php.
*/
Route::prefix('admin')->middleware('jwt')->group(function () {

    // Multipart: an image and an icon.
    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::get('/{id}', [ServiceController::class, 'show']);
        Route::post('/{id}', [ServiceController::class, 'update']);
        Route::delete('/{id}', [ServiceController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        // Candidates for the parent picker; ?exclude={id} when editing.
        Route::get('/parents', [CategoryController::class, 'parents']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });

    // Multipart: images. Categories, variants and specifications are written
    // with the product itself, not through endpoints of their own.
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        // Before /{id}: a literal segment must not be read as an id. How often
        // the catalogue is looked at -- ?days=N sets the window.
        Route::get('/stats', [ProductStatsController::class, 'index']);
        Route::get('/{id}/stats', [ProductStatsController::class, 'show']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::post('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

    Route::prefix('packages')->group(function () {
        Route::get('/', [PackageController::class, 'index']);
        // What a detail line may point at: products, charges, services.
        Route::get('/options', [PackageController::class, 'options']);
        Route::post('/', [PackageController::class, 'store']);
        Route::get('/{id}', [PackageController::class, 'show']);
        Route::put('/{id}', [PackageController::class, 'update']);
        Route::delete('/{id}', [PackageController::class, 'destroy']);
    });

    /*
     * A named set of products the storefront shows together. Written whole,
     * like a package: the heading and the products in one call. Plain PUT on
     * update -- nothing here carries a file, since the products bring their
     * own images.
     */
    Route::prefix('product-highlights')->group(function () {
        Route::get('/', [ProductHighlightController::class, 'index']);
        // The product picker. Before /{id}: a literal segment must not be
        // read as an id.
        Route::get('/options', [ProductHighlightController::class, 'options']);
        Route::post('/', [ProductHighlightController::class, 'store']);
        Route::get('/{id}', [ProductHighlightController::class, 'show']);
        Route::put('/{id}', [ProductHighlightController::class, 'update']);
        Route::delete('/{id}', [ProductHighlightController::class, 'destroy']);
    });

    /*
     * Moderation for what buyers wrote about a product. Read, hide, remove --
     * no create and no edit, because a shop that could write or reword its own
     * reviews would not be publishing reviews. ?product_id=N narrows the list
     * to one product.
     */
    Route::prefix('product-reviews')->group(function () {
        Route::get('/', [ProductReviewController::class, 'index']);
        Route::get('/{id}', [ProductReviewController::class, 'show']);
        // The only field an admin may write.
        Route::put('/{id}/publish', [ProductReviewController::class, 'publish']);
        Route::delete('/{id}', [ProductReviewController::class, 'destroy']);
    });

    Route::prefix('other-charges')->group(function () {
        Route::get('/', [OtherChargeController::class, 'index']);
        Route::post('/', [OtherChargeController::class, 'store']);
        Route::get('/{id}', [OtherChargeController::class, 'show']);
        Route::put('/{id}', [OtherChargeController::class, 'update']);
        Route::delete('/{id}', [OtherChargeController::class, 'destroy']);
    });

    // Multipart: a logo.
    Route::prefix('banks')->group(function () {
        Route::get('/', [BankController::class, 'index']);
        Route::post('/', [BankController::class, 'store']);
        Route::get('/{id}', [BankController::class, 'show']);
        Route::post('/{id}', [BankController::class, 'update']);
        Route::delete('/{id}', [BankController::class, 'destroy']);
    });

    // The fare table. Its region lists are the public ones under /api/v1.
    Route::prefix('delivery-prices')->group(function () {
        Route::get('/', [DeliveryPriceController::class, 'index']);
        Route::post('/', [DeliveryPriceController::class, 'store']);
        Route::get('/{id}', [DeliveryPriceController::class, 'show']);
        Route::put('/{id}', [DeliveryPriceController::class, 'update']);
        Route::delete('/{id}', [DeliveryPriceController::class, 'destroy']);
    });

    // Read plus the status move. Orders are created by checkout, never here.
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}/status', [TransactionController::class, 'updateStatus']);
        // Multipart: proof of payment, package photo, delivery note.
        Route::post('/{id}/attachments', [TransactionController::class, 'uploadAttachment']);
    });
});
