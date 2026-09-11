<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAttachment extends Model
{
    protected $table = 'transaction_attachments';

    protected $fillable = [
        'transaction_id',
        'type',
        'file_path',
        'original_name',
        'mime',
        'size',
        'note',
        'uploaded_by',
    ];

    /**
     * Sent with every attachment, so neither the admin screen nor the
     * customer's receipt has to rebuild a path it was never given.
     *
     * file_path is stored relative to the public root; only the server knows
     * what host that is.
     */
    protected $appends = ['url', 'type_label'];

    /** Attachment type slugs and their human labels. */
    const TYPES = [
        'PAYMENT'  => 'Payment Proof',
        'PACKAGE'  => 'Package Photo',
        'DELIVERY' => 'Delivery Proof',
        'OTHER'    => 'Other',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Where the file can actually be fetched from. */
    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? asset($this->file_path) : null;
    }
}
