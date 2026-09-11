<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionHist extends Model
{
    protected $table = 'transaction_hists';

    // Append-only log: created_at only, no updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'status_from',
        'status_code',
        'status_name',
        'description',
        'created_by',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
