<?php

namespace App\Repositories\Commerce;

use App\Models\Transaction;
use App\Models\TransactionAddress;
use App\Models\TransactionAttachment;
use App\Models\TransactionBenefit;
use App\Models\TransactionCharge;
use App\Models\TransactionDetail;
use App\Models\TransactionHist;
use App\Models\TransactionPackage;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * An order and the six tables that hang off it.
 *
 * They are written together or not at all, but the transaction that guarantees
 * that is TransactionService's -- these methods are the individual inserts it
 * runs inside one.
 */
class TransactionRepository extends BaseRepository
{
    protected $model = Transaction::class;

    /** Everything a receipt shows. */
    protected const FULL = [
        'details', 'packages', 'benefits', 'address', 'chargeItems',
        'statusCode', 'paymentTypeCode', 'attachments',
    ];

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)
            ->with('statusCode')
            ->orderByDesc('id')
            ->get();
    }

    /** The admin view, which adds the status history to the receipt's tables. */
    public function findForWebsite($id, $websiteId = null): ?Model
    {
        return $this->forWebsite($websiteId)
            ->with(array_merge(self::FULL, ['histories']))
            ->where('id', $id)
            ->first();
    }

    /**
     * One order by the token printed on its receipt.
     *
     * The token is what stands in for a login: the buyer has no account, so
     * holding the token is the whole of the claim to see the order.
     */
    public function findByToken($token, $websiteId = null): ?Transaction
    {
        return $this->forWebsite($websiteId)
            ->with(self::FULL)
            ->where('receipt_token', $token)
            ->first();
    }

    /**
     * A customer's orders, matched on email plus the last 4 digits of the
     * phone. Lightweight verification, not a login: both must match.
     */
    public function forCustomer(int $websiteId, string $email, string $phoneLast4)
    {
        return $this->forWebsite($websiteId)
            ->where('customer_email', $email)
            ->whereRaw('RIGHT(TRIM(customer_phone), 4) = ?', [$phoneLast4])
            ->with('statusCode')
            ->orderByDesc('id')
            ->get();
    }

    /* =========================================================
     * The rows that belong to an order
     *
     * Each takes the parent id explicitly rather than reading it off a model,
     * so the caller cannot accidentally file a line under the wrong order.
     * ========================================================= */

    public function addDetail($transactionId, array $row): TransactionDetail
    {
        return TransactionDetail::create($row + ['transaction_id' => $transactionId]);
    }

    public function addAddress($transactionId, array $row): TransactionAddress
    {
        return TransactionAddress::create($row + ['transaction_id' => $transactionId]);
    }

    public function addCharge($transactionId, array $row): TransactionCharge
    {
        return TransactionCharge::create($row + ['transaction_id' => $transactionId]);
    }

    /**
     * One charge line, written once and kept in step afterwards.
     *
     * Keyed on the code rather than inserted: the QRIS admin discount is
     * recalculated whenever a payment is raised for the order, and a second
     * visit must adjust that line rather than add another one beside it.
     */
    public function upsertChargeByCode($transactionId, string $code, array $row): TransactionCharge
    {
        $charge = TransactionCharge::where('transaction_id', $transactionId)
            ->where('code', $code)
            ->first();

        if ($charge) {
            $charge->update($row);

            return $charge;
        }

        return TransactionCharge::create($row + ['transaction_id' => $transactionId, 'code' => $code]);
    }

    /**
     * What the charge lines add up to.
     *
     * Read back from the rows rather than adjusted arithmetically, so
     * transactions.charges cannot drift from the lines behind it -- and a
     * negative line is counted like any other.
     */
    public function sumCharges($transactionId): float
    {
        return (float) TransactionCharge::where('transaction_id', $transactionId)->sum('amount');
    }

    /**
     * What the charge lines add up to, ignoring one of them.
     *
     * The QRIS admin discount is recalculated whenever a payment is raised, and
     * it has to be worked out against the order *without* it -- otherwise the
     * second calculation starts from a total the first one already reduced, and
     * the discount shrinks every time.
     */
    public function sumChargesExcept($transactionId, string $code): float
    {
        return (float) TransactionCharge::where('transaction_id', $transactionId)
            ->where('code', '!=', $code)
            ->sum('amount');
    }

    public function addPackage($transactionId, array $row): TransactionPackage
    {
        return TransactionPackage::create($row + ['transaction_id' => $transactionId]);
    }

    public function addBenefit($transactionId, array $row): TransactionBenefit
    {
        return TransactionBenefit::create($row + ['transaction_id' => $transactionId]);
    }

    public function addHistory($transactionId, array $row): TransactionHist
    {
        return TransactionHist::create($row + ['transaction_id' => $transactionId]);
    }

    /**
     * A file kept against an order -- proof of payment from the buyer, or
     * whatever the seller files alongside it.
     */
    public function addAttachment($transactionId, array $row): TransactionAttachment
    {
        return TransactionAttachment::create($row + ['transaction_id' => $transactionId]);
    }

    /**
     * Persist a column set on an already-loaded order.
     *
     * save() rather than update() because the receipt number is written
     * straight onto the model after the insert, when the id it is derived from
     * finally exists.
     */
    public function save(Transaction $transaction): bool
    {
        return (bool) $transaction->save();
    }
}
