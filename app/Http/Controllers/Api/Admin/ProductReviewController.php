<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Commerce\ProductReviewService;
use Illuminate\Http\Request;

/**
 * Moderation for the reviews buyers leave on products.
 *
 * Read, hide, remove -- and nothing else. A review is somebody else's words
 * about something they bought, so the only decisions a seller makes about one
 * are whether it stays up and whether it stays at all. There is deliberately
 * no create and no edit: a shop that could write its own five-star reviews,
 * or improve the wording of a customer's, would be publishing something other
 * than reviews, and the "verified purchase" the storefront prints beside each
 * one would stop being true.
 *
 * That is the same stance ContentCommentController takes in website-service,
 * for the same reason.
 *
 * Hiding is the first resort and deleting the second: an unpublished review
 * keeps the record of what was said while taking it off the page.
 */
class ProductReviewController extends ApiController
{
    public $service;

    public function __construct(ProductReviewService $service)
    {
        $this->service = $service;
    }

    /** Every review of this website, newest first. ?product_id= narrows it. */
    public function index(Request $request)
    {
        $productId = $request->query('product_id');

        return $this->items(
            $this->service->getList(admin_website_id(), $productId ? (int) $productId : null)
        );
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Review');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Show or hide one review.
     *
     * Its own endpoint rather than a general update, because it is the only
     * field an admin may write -- a PUT that took the whole row would be a PUT
     * that could rewrite what the buyer said.
     */
    public function publish(Request $request, $id)
    {
        $data = $request->validate([
            'is_published' => 'required|boolean',
        ]);

        return $this->respond(
            $this->service->setPublished(
                $id,
                (bool) $data['is_published'],
                admin_website_id(),
                acting_user_email()
            )
        );
    }

    public function destroy($id)
    {
        return $this->respond($this->service->delete($id, admin_website_id()));
    }
}
