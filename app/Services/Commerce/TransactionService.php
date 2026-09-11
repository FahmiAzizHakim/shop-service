<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\TransactionRepository;
use App\Repositories\Reference\CodeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    protected $transactions;
    protected $codes;

    public function __construct(TransactionRepository $transactions, CodeRepository $codes)
    {
        $this->transactions = $transactions;
        $this->codes        = $codes;
    }

    /* =========================
     * STORE a full order atomically (header + details + address + charges +
     * packages + benefits), generate a receipt number & token, and seed the
     * first history row.
     *
     * @param array $header   transactions columns (website_id, customer_*, money, status ...)
     * @param array $items    list of transaction_details rows
     * @param array|null $address  transaction_addresses row (without transaction_id)
     * @param array $charges  list of transaction_charges rows
     * @param array $packages list of transaction_packages rows
     * @param array $benefits list of transaction_benefits rows
     * ========================= */
    public function store(array $header, array $items, ?array $address = null, array $charges = [], array $packages = [], array $benefits = [])
    {
        DB::beginTransaction();
        try {
            $header['status']        = $header['status'] ?? 'STSPY';
            $header['receipt_token'] = $header['receipt_token'] ?? Str::upper(Str::random(40));

            $trx = $this->transactions->create($header);

            // Human-readable receipt number derived from the id, unique per website.
            if (empty($trx->receipt_no)) {
                $datePart = optional($trx->transaction_date)->format('Ymd') ?? date('Ymd');
                $trx->receipt_no = 'INV-' . $datePart . '-' . str_pad($trx->id, 6, '0', STR_PAD_LEFT);
                $this->transactions->save($trx);
            }

            foreach ($items as $item) {
                $this->transactions->addDetail($trx->id, $item);
            }

            if ($address) {
                $this->transactions->addAddress($trx->id, $address);
            }

            foreach ($charges as $charge) {
                $this->transactions->addCharge($trx->id, $charge);
            }

            foreach ($packages as $package) {
                $this->transactions->addPackage($trx->id, $package);
            }

            foreach ($benefits as $benefit) {
                $this->transactions->addBenefit($trx->id, $benefit);
            }

            // Seed the initial status history.
            $code = $this->codes->findInGroup('STS', $trx->status);
            $this->transactions->addHistory($trx->id, [
                'status_from' => null,
                'status_code' => $trx->status,
                'status_name' => $code->name ?? $trx->status,
                'description' => $header['hist_description'] ?? 'Transaction created',
                'created_by'  => $header['created_by'] ?? null,
            ]);

            DB::commit();
            return array("status" => "success", "message" => "Transaction created", "data" => $trx);
        } catch (\Throwable $e) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create transaction: " . $e->getMessage());
        }
    }

    /* =========================
     * GET a single transaction by its public receipt token (scoped to website).
     * ========================= */
    public function getByToken($token, $websiteId = null)
    {
        return $this->transactions->findByToken($token, $websiteId);
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     * ========================= */
    public function getList($websiteId = null)
    {
        return $this->transactions->listForWebsite($websiteId);
    }

    /* =========================
     * GET SINGLE ROW with all related data (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->transactions->findForWebsite($id, $websiteId);
    }

    /* =========================
     * ATTACH a file to a placed order (payment proof, or a seller's document).
     *
     * The upload itself has already happened by the time this is called --
     * UploadService moved the file and the caller passes on where it landed.
     * ========================= */
    public function addAttachment($transactionId, array $params)
    {
        return $this->transactions->addAttachment($transactionId, $params);
    }

    /* =========================
     * QRIS: the admin fee, settled once the nudge is known
     * ========================= */

    /**
     * Bring the order's admin fee down to what the customer will actually pay.
     *
     * Checkout quotes a flat fee -- 200 -- because the real cost is not knowable
     * yet: Qrisly takes 100 per payment and then nudges the amount by 1..99 so
     * payments of equal value can be told apart, and that nudge only exists
     * once a payment is generated. Charging the sum would mean quoting a figure
     * nobody can compute at checkout, and surcharging a few rupiah for a
     * "unique code" the customer never asked for.
     *
     * So the difference comes back here as a discount, written as a second
     * charge line with a negative amount:
     *
     *   quoted 200, Qrisly nudged by 20  ->  discount 80  ->  admin cost 120
     *
     * The customer is quoted a round number and always pays less than quoted.
     *
     * $paidAmount is what the QRIS actually asks for -- Qrisly's final_amount.
     * The discount is the gap between that and the order, and it is clamped to
     * the provider fee: the most that can ever come back is the 100 the order
     * was padded by, so a caller cannot talk the total down by naming a small
     * figure.
     *
     * Idempotent. Regenerating a payment recalculates the same line rather
     * than adding another, so an order carries one discount however many times
     * this runs.
     *
     * @param  \App\Models\Transaction  $trx
     */
    public function applyQrisAdjustment($trx, float $paidAmount): array
    {
        if ($trx->payment_type !== 'TRTQR') {
            return ['status' => 'failed', 'message' => 'This order is not being paid by QRIS'];
        }

        $config = config('checkout.qris_fee');
        $cap    = (float) ($config['provider_fee'] ?? 0);

        /*
         * Worked out against the order *without* its own adjustment.
         *
         * Using the current grandtotal would mean the second calculation
         * starts from a figure the first one already reduced: a payment
         * regenerated with a different nudge would shrink the discount instead
         * of replacing it, and the total would drift away from what is being
         * asked for. So the discount line is left out of the base and put back
         * at the end.
         */
        $chargesExcludingAdjustment = $this->transactions->sumChargesExcept(
            $trx->id,
            $config['discount_code']
        );

        $base = (float) $trx->price
            - (float) $trx->discount
            + (float) $trx->delivery_fee
            + $chargesExcludingAdjustment
            + (float) $trx->tax;

        $discount = round($base - $paidAmount, 2);

        // Nothing to give back: the QRIS asks for the order's total or more,
        // which happens when the fee is switched off or the order predates
        // this scheme. Not an error -- there is simply no adjustment.
        if ($discount <= 0) {
            return [
                'status'  => 'success',
                'message' => 'No adjustment needed',
                'data'    => $this->qrisFigures($trx, 0.0),
            ];
        }

        // The order was padded by exactly (quoted - provider_fee), so that is
        // the ceiling. Anything beyond it would be discounting the goods.
        $discount = min($discount, $cap);

        DB::beginTransaction();

        try {
            $this->transactions->upsertChargeByCode($trx->id, $config['discount_code'], [
                'name'       => $config['discount_name'],
                // Negative: it is a charge line that gives money back, which is
                // what keeps the quote and the refund visible as two lines
                // instead of one netted figure nobody can explain.
                'amount'     => -1 * $discount,
                'remark'     => 'Selisih biaya admin QRIS',
                'updated_by' => 'system',
            ]);

            // Read back from the lines rather than adjusted in place, so the
            // column and the rows behind it cannot drift.
            $charges = $this->transactions->sumCharges($trx->id);
            $total   = (float) $trx->price - (float) $trx->discount + (float) $trx->delivery_fee + $charges;

            $this->transactions->update($trx, [
                'charges'    => $charges,
                'total'      => $total,
                'grandtotal' => $total + (float) $trx->tax,
                'updated_by' => 'system',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['status' => 'failed', 'message' => 'Failed to adjust the QRIS admin fee'];
        }

        return [
            'status'  => 'success',
            'message' => 'QRIS admin fee adjusted',
            'data'    => $this->qrisFigures($trx->refresh(), $discount),
        ];
    }

    /** What a caller needs back: the order's money after the adjustment. */
    protected function qrisFigures($trx, float $discount): array
    {
        return [
            'discount'   => $discount,
            'charges'    => (float) $trx->charges,
            'total'      => (float) $trx->total,
            'grandtotal' => (float) $trx->grandtotal,
        ];
    }

    /* =========================
     * PAYMENT PROOF: store the file and move the order on
     * ========================= */

    /** Waiting for the buyer's money. */
    public const STATUS_AWAITING_PAYMENT = 'STSPY';

    /** Proof uploaded, waiting for someone to check it. */
    public const STATUS_PAYMENT_CONFIRMATION = 'STSCP';

    /** The money is in. Set by markQrisPaid() when Qrisly confirms a payment. */
    public const STATUS_PAID = 'STSPD';

    /**
     * The attachment type that is a claim to have paid.
     *
     * One of TransactionAttachment::TYPES, and the only one that moves the
     * status: a package photo or a delivery note says nothing about money.
     */
    public const PROOF_TYPE = 'PAYMENT';

    /**
     * Keep a buyer's proof of payment, and put the order in the queue for it
     * to be checked.
     *
     * Storing the file and moving the status are one act, not two: an order
     * with a receipt attached that still reads "waiting for payment" is
     * invisible to whoever settles them, which is the whole reason the status
     * exists. So both happen in one transaction and a failure keeps neither.
     *
     * Only a PAYMENT attachment moves anything -- a photo of the package is
     * not a claim to have paid -- and only from "awaiting payment". An order
     * already paid, delivered or cancelled keeps the status it has: a customer
     * uploading a second file must not walk it backwards, and re-uploading
     * while already awaiting confirmation is not a change.
     *
     * @param  \App\Models\Transaction  $trx
     */
    public function recordPaymentProof($trx, array $attachment, ?string $by = null): array
    {
        $advance = ($attachment['type'] ?? 'PAYMENT') === self::PROOF_TYPE
            && $trx->status === self::STATUS_AWAITING_PAYMENT;

        try {
            $saved = DB::transaction(function () use ($trx, $attachment, $by, $advance) {
                $row = $this->transactions->addAttachment($trx->id, $attachment);

                if (!$row) {
                    throw new \RuntimeException('Gagal menyimpan bukti pembayaran.');
                }

                if ($advance) {
                    // updateStatus writes the history row and checks the code
                    // against the STS group; inside this transaction its own
                    // commit is a savepoint, so a throw below still undoes it.
                    $result = $this->updateStatus(
                        $trx->id,
                        self::STATUS_PAYMENT_CONFIRMATION,
                        'Bukti pembayaran diunggah pembeli',
                        $by
                    );

                    if (($result['status'] ?? '') !== 'success') {
                        throw new \RuntimeException($result['message'] ?? 'Gagal memperbarui status.');
                    }
                }

                return $row;
            });
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        // Read back rather than assumed: when the order was already past
        // payment this is the status it kept, which is what the caller should
        // report to the customer.
        $current = $this->transactions->find($trx->id);
        $code    = $this->codes->findInGroup('STS', $current->status);

        return [
            'status'  => 'success',
            'message' => 'Bukti berhasil diunggah. Terima kasih!',
            'data'    => $saved,
            'transaction_status' => [
                'code'        => $current->status,
                'name'        => $code->name ?? $current->status,
                'description' => $code->description ?? null,
                'advanced'    => $advance,
            ],
        ];
    }

    /**
     * Mark a QRIS order paid, because the provider says the money arrived.
     *
     * The counterpart to recordPaymentProof: that one records a customer's
     * *claim* to have paid and moves the order into a queue for a human to
     * check, because a photo of a transfer slip proves nothing on its own.
     * This one is the provider's own verdict, so it settles the order outright
     * and no one has to look.
     *
     * Called by the gateway after Qrisly has confirmed the payment, and only
     * then. Nothing a customer sends reaches this: the route it sits behind
     * takes no amount and no status from the caller, so holding a receipt
     * token is not a way to declare your own order paid.
     *
     * Idempotent, and one-way. An order already paid answers success without
     * writing anything -- a second check of a settled payment must not add a
     * history row every time a page polls -- and an order past paying
     * (delivered, completed, cancelled) keeps the status it has rather than
     * being walked backwards to STSPD.
     *
     * @param  \App\Models\Transaction  $trx
     */
    public function markQrisPaid($trx, ?string $by = null): array
    {
        if ($trx->payment_type !== 'TRTQR') {
            return ['status' => 'failed', 'message' => 'This order is not being paid by QRIS'];
        }

        if ($trx->status === self::STATUS_PAID) {
            return [
                'status'  => 'success',
                'message' => 'Order was already paid',
                'data'    => ['status' => $trx->status, 'changed' => false],
            ];
        }

        // Only from the two statuses that mean "still waiting for the money".
        // Anything further on has been acted upon by a person, and a payment
        // check is not the thing to undo that.
        if (!in_array($trx->status, [self::STATUS_AWAITING_PAYMENT, self::STATUS_PAYMENT_CONFIRMATION], true)) {
            return [
                'status'  => 'success',
                'message' => 'Order is past payment; status left as it is',
                'data'    => ['status' => $trx->status, 'changed' => false],
            ];
        }

        $result = $this->updateStatus(
            $trx->id,
            self::STATUS_PAID,
            'Pembayaran QRIS dikonfirmasi provider',
            $by ?: 'system'
        );

        if (($result['status'] ?? '') !== 'success') {
            return $result;
        }

        return [
            'status'  => 'success',
            'message' => 'Order marked as paid',
            'data'    => ['status' => self::STATUS_PAID, 'changed' => true],
        ];
    }

    /* =========================
     * STATUS OPTIONS (codes where parentcode = STS)
     * ========================= */
    public function statusOptions()
    {
        return $this->codes->children('STS');
    }

    /* =========================
     * UPDATE STATUS + write history row
     * ========================= */
    public function updateStatus($id, $statusCode, $description = null, $changedBy = null)
    {
        DB::beginTransaction();

        $trx = $this->transactions->find($id);
        if (!$trx) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Transaction not found");
        }

        // Validate the target status belongs to the STS group.
        $code = $this->codes->findInGroup('STS', $statusCode);
        if (!$code) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Invalid status");
        }

        $from = $trx->status;

        $this->transactions->update($trx, [
            'status'     => $code->code,
            'updated_by' => $changedBy,
        ]);

        $this->transactions->addHistory($trx->id, [
            'status_from' => $from,
            'status_code' => $code->code,
            'status_name' => $code->name,
            'description' => $description,
            'created_by'  => $changedBy,
        ]);

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Status updated to " . $code->name,
            "data"    => $trx,
        );
    }
}
