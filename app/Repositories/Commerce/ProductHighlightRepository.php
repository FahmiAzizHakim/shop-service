<?php

namespace App\Repositories\Commerce;

use App\Models\ProductHighlight;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;

class ProductHighlightRepository extends BaseRepository
{
    protected $model = ProductHighlight::class;

    /** What an editor sees: the lines, and the product behind each one. */
    protected const RELATIONS = ['details.product'];

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)
            ->with(self::RELATIONS)
            ->ordered()
            ->get();
    }

    public function findForWebsite($id, $websiteId = null): ?Model
    {
        return $this->forWebsite($websiteId)->with(self::RELATIONS)->where('id', $id)->first();
    }

    /**
     * What the storefront renders: the running highlights, each with the
     * products a card needs.
     *
     * The products are constrained the same way the public catalogue
     * constrains them -- active, under an active service -- so a highlight can
     * never put a product on the page that the catalogue is hiding. A
     * highlight left holding nothing but hidden products comes back empty
     * rather than as a heading over a blank row, which is what
     * CatalogDataService::highlights() then drops.
     */
    public function activeForWebsite($websiteId)
    {
        return $this->forWebsite($websiteId)
            ->active()
            ->with(['products' => function ($q) {
                $q->where('products.is_active', true)
                    ->whereHas('service', fn ($s) => $s->where('is_active', true))
                    ->with([
                        'images' => fn ($i) => $i->where('is_active', true),
                        'variants' => fn ($v) => $v->where('is_active', true),
                        'specifications',
                        'service',
                    ]);
            }])
            ->ordered()
            ->get();
    }

    public function deleteDetails(ProductHighlight $highlight): void
    {
        $highlight->details()->delete();
    }
}
