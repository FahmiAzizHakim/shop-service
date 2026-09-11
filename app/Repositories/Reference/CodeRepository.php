<?php

namespace App\Repositories\Reference;

use App\Models\Code;
use App\Repositories\BaseRepository;

/**
 * The shared codes table: a flat list of parent/child pairs standing in for
 * every small enumeration in the app (order statuses under STS, and so on).
 *
 * Copied into each service database, so a status name never needs a
 * cross-service call to read.
 */
class CodeRepository extends BaseRepository
{
    protected $model = Code::class;

    /** Every child code of a parent, in display order. */
    public function children(string $parentcode)
    {
        return $this->query()->children($parentcode)->get();
    }

    /**
     * One code within its group, or null.
     *
     * Null is how a caller learns a submitted code is not a real one -- the
     * group is part of the lookup, so a valid code from another group does not
     * pass.
     */
    public function findInGroup(string $parentcode, $code): ?Code
    {
        return $this->query()
            ->where('parentcode', $parentcode)
            ->where('code', $code)
            ->first();
    }
}
