<?php

namespace App\Repositories\Commerce;

use App\Models\Bank;
use App\Repositories\BaseRepository;

class BankRepository extends BaseRepository
{
    protected $model = Bank::class;

    /** Ordered by name: this list is read by a customer, not by an admin. */
    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)->orderBy('bank_name')->get();
    }

    /**
     * The accounts a buyer is actually asked to pay into.
     *
     * A deactivated account stays on the books but must not reach a receipt,
     * which is the whole difference between this and listForWebsite().
     */
    public function activeForWebsite($websiteId)
    {
        return $this->forWebsite($websiteId)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get();
    }
}
