<?php

namespace App\Repositories\Commerce;

use App\Models\Category;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class CategoryRepository extends BaseRepository
{
    protected $model = Category::class;

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)->with('parent')->orderBy('id')->get();
    }

    /**
     * Candidates for the parent dropdown. $excludeId keeps a category from
     * being offered as its own parent.
     */
    public function parentOptions($websiteId = null, $excludeId = null)
    {
        return $this->forWebsite($websiteId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('category_name')
            ->get();
    }

    public function countChildren(Category $category): int
    {
        return $category->children()->count();
    }

    /**
     * How many products are filed under this category.
     *
     * Read off the pivot directly: the count is all the caller wants, and
     * loading the products to count them would read the catalogue for nothing.
     */
    public function countProductsUsing($id): int
    {
        return DB::table('product_categories')->where('category_id', $id)->count();
    }
}
