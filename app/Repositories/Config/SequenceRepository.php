<?php

namespace App\Repositories\Config;

use App\Models\Config\Sequence;
use App\Models\Config\SequenceFormat;
use App\Repositories\BaseRepository;

/**
 * Document numbering: the format a prefix is written in, and the counter that
 * has got as far as it has.
 *
 * A counter is one row per (name, year, month, entity), so a monthly-reset
 * format simply gets a new row when the month turns over rather than having
 * its number reset in place.
 */
class SequenceRepository extends BaseRepository
{
    protected $model = Sequence::class;

    /** How a prefix is composed, or null when nothing defines it. */
    public function findFormat($prefixName): ?SequenceFormat
    {
        return SequenceFormat::where('sequence_name', $prefixName)->first();
    }

    public function saveFormat(SequenceFormat $format, array $attributes): bool
    {
        return (bool) $format->update($attributes);
    }

    /**
     * The counter for one period.
     *
     * A blank entity is stored inconsistently -- some rows hold '', some
     * NULL -- so both are matched rather than only the one that happened to be
     * written first.
     */
    public function findCounter($sequenceName, $year, $month, $entity = null): ?Sequence
    {
        $query = $this->query()
            ->where('sequence_name', $sequenceName)
            ->where('year', $year)
            ->where('month', $month);

        if ($entity !== null && $entity !== '') {
            $query->where('entitycode', $entity);
        } else {
            $query->where(fn ($q) => $q->where('entitycode', '')->orWhereNull('entitycode'));
        }

        return $query->first();
    }

    /** Move one period's counter on by a step. */
    public function advanceCounter($sequenceName, $year, $month, int $nextValue): int
    {
        return $this->query()
            ->where('sequence_name', $sequenceName)
            ->where('year', $year)
            ->where('month', $month)
            ->update([
                'cur_value'  => $nextValue,
                'updated_at' => now(),
            ]);
    }
}
