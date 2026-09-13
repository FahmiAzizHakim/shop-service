<?php

namespace App\Services\Commerce;

use App\Models\ProductHighlight;
use App\Repositories\Commerce\ProductHighlightDetailRepository;
use App\Repositories\Commerce\ProductHighlightRepository;
use App\Repositories\Commerce\ProductRepository;
use Illuminate\Support\Facades\DB;

/**
 * Highlights: a named set of products, written whole.
 *
 * The set arrives as a list of product ids rather than as the addressable
 * `details` rows a package is edited through, and the difference follows from
 * the tables. A package line carries a qty and a type, so it has an identity
 * worth keeping and editing; a highlight line carries a product id and nothing
 * else, so the line *is* the id. Sending the whole set and reconciling means
 * an editor who ticks and unticks boxes gets what they see, and re-saving the
 * same set writes nothing.
 */
class ProductHighlightService
{
    protected $highlights;
    protected $details;
    protected $products;

    public function __construct(
        ProductHighlightRepository $highlights,
        ProductHighlightDetailRepository $details,
        ProductRepository $products
    ) {
        $this->highlights = $highlights;
        $this->details    = $details;
        $this->products   = $products;
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     * ========================= */
    public function getList($websiteId = null)
    {
        return $this->highlights->listForWebsite($websiteId);
    }

    /* =========================
     * GET SINGLE ROW (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->highlights->findForWebsite($id, $websiteId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create($params, array $productIds = [])
    {
        DB::beginTransaction();

        $highlight = $this->highlights->create($params);
        if (!$highlight) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create highlight");
        }

        $this->syncProducts($highlight, $productIds);

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Highlight created successfully",
            "data"    => $this->highlights->findForWebsite($highlight->id, $highlight->website_id),
        );
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id, $params, array $productIds = [])
    {
        DB::beginTransaction();

        $highlight = $this->highlights->find($id);
        if (!$highlight) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Highlight not found");
        }

        $this->highlights->update($highlight, $params);
        $this->syncProducts($highlight, $productIds);

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Highlight updated successfully",
            "data"    => $this->highlights->findForWebsite($highlight->id, $highlight->website_id),
        );
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $highlight = $this->highlights->find($id);

        if (!$highlight) {
            return array("status" => "failed", "message" => "Highlight not found");
        }

        DB::beginTransaction();
        // The foreign key cascades, but the lines are dropped explicitly so
        // the delete path says what it removes -- as PackageService does.
        $this->highlights->deleteDetails($highlight);
        $this->highlights->delete($highlight);
        DB::commit();

        return array("status" => "success", "message" => "Highlight deleted successfully");
    }

    /**
     * Make the highlight's lines match the submitted set of product ids.
     *
     * Ids are deduplicated and filtered to the highlight's own website -- a
     * product belongs to a website through its service, which the product
     * repository knows how to follow. The FormRequest checks the same thing,
     * and this checks it again: a service is the layer that decides what may
     * be written, and one highlight holding another site's product would not
     * announce itself.
     *
     * Rows already present are left alone rather than deleted and re-inserted,
     * so created_at keeps saying when a product joined the set and re-saving
     * an unchanged highlight writes nothing.
     */
    private function syncProducts(ProductHighlight $highlight, array $productIds): void
    {
        $wanted = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;

            if (!$productId || isset($wanted[$productId])) {
                continue;
            }

            if (!$this->products->belongsToWebsite($productId, $highlight->website_id)) {
                continue;
            }

            $wanted[$productId] = true;
        }

        $wanted = array_keys($wanted);

        $this->details->deleteForHighlightExcept($highlight->id, $wanted);

        $existing = $this->details->productIdsFor($highlight->id);

        foreach (array_diff($wanted, $existing) as $productId) {
            $this->details->create([
                'product_highlight_id' => $highlight->id,
                'product_id'           => $productId,
            ]);
        }
    }
}
