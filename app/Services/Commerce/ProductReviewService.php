<?php

namespace App\Services\Commerce;

use App\Models\ProductReview;
use App\Models\Transaction;
use App\Repositories\Commerce\ProductReviewImageRepository;
use App\Repositories\Commerce\ProductReviewRepository;
use App\Services\Helper\UploadService;
use Illuminate\Support\Facades\DB;

/**
 * Reviews: what a buyer said about a product they bought.
 *
 * Submission is the whole of the interesting part. Everything else here reads
 * rows or takes one down.
 *
 * A review is only ever accepted against an order, and this class is where
 * that is decided -- the controller resolves a receipt token into a
 * transaction and hands it over, and the three questions that make a review
 * legitimate are answered below, in one place, so no future endpoint can
 * answer them differently:
 *
 *   1. has the order got far enough to be a purchase   (REVIEWABLE_STATUSES)
 *   2. was this product actually on it                 (its transaction_details)
 *   3. has this order already reviewed this product    (the unique pair)
 *
 * The second is worth a note: package contents are exploded into
 * transaction_details at price 0 when an order is placed, so a product that
 * arrived inside a package is on the order like any other line and is
 * reviewable like any other line. That falls out of reading the details table
 * rather than the package rows, and is the behaviour you want -- the buyer
 * received the thing either way.
 */
class ProductReviewService
{
    protected $reviews;
    protected $images;
    protected $upload;

    /** Where a review's photos are filed under the shared document root. */
    protected const UPLOAD_PATH = 'review';

    public function __construct(
        ProductReviewRepository $reviews,
        ProductReviewImageRepository $images,
        UploadService $upload
    ) {
        $this->reviews = $reviews;
        $this->images  = $images;
        $this->upload  = $upload;
    }

    /* =========================
     * READS
     * ========================= */

    /** The moderation list. ?product_id= narrows it to one product. */
    public function getList($websiteId = null, $productId = null)
    {
        return $this->reviews->listForWebsite($websiteId, $productId);
    }

    public function getRow($id, $websiteId = null)
    {
        return $this->reviews->findForWebsite($id, $websiteId);
    }

    /** A product's published reviews, with the average and the count. */
    public function forProduct($websiteId, $productId): array
    {
        return [
            'summary' => $this->reviews->summaryForProduct($websiteId, $productId),
            'reviews' => $this->reviews->publishedForProduct($websiteId, $productId),
        ];
    }

    /** What one receipt has already reviewed. */
    public function forTransaction(Transaction $trx)
    {
        return $this->reviews->forTransaction($trx->id);
    }

    /* =========================
     * SUBMIT
     * ========================= */

    /**
     * Record a buyer's review of one product on their order.
     *
     * $trx has already been resolved from the receipt token, so holding it is
     * the proof that the caller is the buyer. What is checked here is whether
     * this particular review is one they are entitled to leave.
     *
     * Each refusal answers a failed status with the reason, which
     * ApiController turns into a 422 -- the shape every other write in this
     * service answers with, so the receipt page renders these beside its
     * validation errors.
     *
     * @param  array  $files  The uploaded photos, possibly empty.
     */
    public function submit(Transaction $trx, array $params, array $files = [])
    {
        $productId = (int) ($params['product_id'] ?? 0);

        if (!in_array($trx->status, ProductReview::REVIEWABLE_STATUSES, true)) {
            return array(
                "status"  => "failed",
                "message" => "This order cannot be reviewed yet.",
            );
        }

        if (!$this->orderContains($trx, $productId)) {
            return array(
                "status"  => "failed",
                "message" => "That product is not on this order.",
            );
        }

        if ($this->reviews->existsForTransactionProduct($trx->id, $productId)) {
            // Refused rather than overwritten: a review is a statement made at
            // a moment, and silently replacing one would let a receipt be
            // re-submitted until the wording suited somebody.
            return array(
                "status"  => "failed",
                "message" => "You have already reviewed this product on this order.",
            );
        }

        // Store the photos before the transaction opens: moving files is not
        // something a rollback can undo, so a failure here must happen while
        // there is still nothing to roll back.
        $stored = $this->storeImages($files);

        DB::beginTransaction();

        $review = $this->reviews->create([
            'website_id'     => $trx->website_id,
            'product_id'     => $productId,
            'transaction_id' => $trx->id,
            // Snapshot: what the order says the buyer is called, unless they
            // gave a name with the review.
            'reviewer_name'  => ($params['reviewer_name'] ?? null) ?: ($trx->customer_name ?: 'Anonymous'),
            'rating'         => (int) $params['rating'],
            'review_text'    => $params['review_text'] ?? null,
            'is_published'   => true,
            'created_by'     => $trx->customer_email ?: 'customer',
        ]);

        if (!$review) {
            DB::rollBack();
            $this->deleteImageFiles($stored);

            return array("status" => "failed", "message" => "Failed to save review");
        }

        foreach ($stored as $image) {
            $this->images->create([
                'product_review_id' => $review->id,
                'image_name'        => $image['image_name'],
                'image_url'         => $image['image_url'],
            ]);
        }

        DB::commit();

        return array(
            "status"  => "success",
            "message" => "Thank you for your review",
            "data"    => $review->fresh('images'),
        );
    }

    /* =========================
     * MODERATION
     * ========================= */

    /**
     * Show or hide a review.
     *
     * The gentler half of moderation: the words stay, the page stops carrying
     * them. Deleting is for what should not exist at all.
     */
    public function setPublished($id, bool $published, $websiteId = null, $actor = null)
    {
        $review = $this->reviews->findForWebsite($id, $websiteId);

        if (!$review) {
            return array("status" => "failed", "message" => "Review not found");
        }

        $this->reviews->update($review, [
            'is_published' => $published,
            'updated_by'   => $actor,
        ]);

        return array(
            "status"  => "success",
            "message" => $published ? "Review published" : "Review hidden",
            "data"    => $review->fresh('images'),
        );
    }

    public function delete($id, $websiteId = null)
    {
        $review = $this->reviews->findForWebsite($id, $websiteId);

        if (!$review) {
            return array("status" => "failed", "message" => "Review not found");
        }

        $files = $review->images->all();

        DB::beginTransaction();
        $this->images->deleteForReview($review->id);
        $this->reviews->delete($review);
        DB::commit();

        // After the commit: a file removed for a delete that then rolled back
        // would be a file gone from a review that still exists.
        $this->deleteImageFiles($files);

        return array("status" => "success", "message" => "Review deleted successfully");
    }

    /**
     * Remove every review of a product, for the product's own delete path.
     *
     * Called inside ProductService::delete()'s transaction, so the rows go
     * with it; the files are removed by the caller afterwards, which is why
     * this hands them back rather than unlinking them itself.
     */
    public function deleteForProduct($productId): array
    {
        $reviews = $this->reviews->forProduct($productId);

        if ($reviews->isEmpty()) {
            return [];
        }

        $files = $reviews->flatMap(fn ($review) => $review->images)->all();

        $this->images->deleteForReviewIds($reviews->pluck('id')->all());
        $this->reviews->query()->where('product_id', $productId)->delete();

        return $files;
    }

    /**
     * Remove stored photos from disk. Public so the product delete path can
     * clear the files it was handed by deleteForProduct().
     */
    public function removeImageFiles($images): void
    {
        $this->deleteImageFiles($images);
    }

    /* =========================
     * INTERNALS
     * ========================= */

    /**
     * Was this product on this order?
     *
     * Read off transaction_details, which is where every product on an order
     * ends up -- bought on its own or exploded out of a package.
     */
    private function orderContains(Transaction $trx, $productId): bool
    {
        if (!$productId) {
            return false;
        }

        return $trx->details()->where('product_id', $productId)->exists();
    }

    /**
     * Move the uploaded photos into the document root.
     *
     * An upload that fails is skipped rather than fatal: a buyer who took the
     * trouble to write a review should not lose it because one of four photos
     * would not store.
     *
     * @return array<int, array<string, string>>
     */
    private function storeImages(array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $up = $this->upload->store($file, self::UPLOAD_PATH);

            if (!$up) {
                continue;
            }

            $stored[] = [
                'image_name' => $up['original'] ?? 'image',
                'image_url'  => $up['uploaded'],
            ];
        }

        return $stored;
    }

    /**
     * Remove stored photos from disk.
     *
     * Resolved against the same root UploadService writes to -- the shared
     * document root, which is the gateway's public/ rather than this
     * application's, so public_path() would look in the wrong place.
     */
    private function deleteImageFiles($images): void
    {
        $root = rtrim(
            env('UPLOAD_PUBLIC_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? public_path()),
            '/'
        );

        foreach ($images as $image) {
            $url = is_array($image) ? ($image['image_url'] ?? null) : $image->image_url;

            if (!$url || !str_starts_with($url, 'uploads/')) {
                continue;
            }

            $path = $root . '/' . $url;

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
