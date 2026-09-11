<?php

namespace App\Http\Controllers\Api;

use App\Models\TransactionAttachment;
use App\Services\Checkout\CheckoutService;
use App\Services\Helper\UploadService;
use App\Services\Commerce\BankService;
use App\Services\Commerce\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The buyer's side of a placed order: the receipt, the accounts to pay into,
 * and uploading proof of payment.
 *
 * A receipt is addressed by its unguessable token rather than its id, which is
 * what makes these endpoints safe to leave public. The order list is behind a
 * lightweight check instead -- email plus the last 4 digits of the phone.
 */
class OrderController extends ApiController
{
    protected $transactions;
    protected $checkout;
    protected $banks;

    public function __construct(
        TransactionService $transactions,
        CheckoutService $checkout,
        BankService $banks
    ) {
        $this->transactions = $transactions;
        $this->checkout     = $checkout;
        $this->banks        = $banks;
    }

    /**
     * A customer's own orders. Not a login: both fields must match.
     */
    public function lookup(Request $request, $website)
    {
        $data = $request->validate([
            'email'       => 'required|email|max:191',
            'phone_last4' => 'required|digits:4',
        ]);

        return $this->items(
            $this->checkout->ordersFor((int) $website, $data['email'], $data['phone_last4'])
        );
    }

    /**
     * One order in full, plus the accounts it can be paid into.
     */
    public function receipt($website, $token)
    {
        $trx = $this->transactions->getByToken($token, (int) $website);

        if (!$trx) {
            return $this->notFound('Order');
        }

        return response()->json([
            'data' => [
                'transaction' => $trx,
                'banks'       => $this->banks->getPublicList((int) $website),
            ],
        ]);
    }

    /**
     * Settle the QRIS admin fee against what the payment actually asks for.
     *
     * Called by the gateway once thirdparty-service has raised a QRIS, because
     * only then is Qrisly's nudge known. Checkout quoted a flat fee; this
     * writes back the difference as a discount line, so the order's total ends
     * up equal to the figure the customer will scan and pay.
     *
     * Addressed by the receipt token like everything else here, and reachable
     * only by the gateway (X-Gateway-Token gates every route in this service).
     * What it will accept is bounded rather than trusted: the discount can
     * never exceed the provider fee the order was padded by, so naming a small
     * paid_amount cannot discount the goods -- see
     * TransactionService::applyQrisAdjustment.
     */
    public function qrisAdjustment(Request $request, $website, $token)
    {
        $trx = $this->transactions->getByToken($token, (int) $website);

        if (!$trx) {
            return $this->notFound('Order');
        }

        $data = $request->validate([
            // What the QRIS asks for: Qrisly's final_amount.
            'paid_amount' => 'required|numeric|min:0',
        ]);

        return $this->respond(
            $this->transactions->applyQrisAdjustment($trx, (float) $data['paid_amount'])
        );
    }

    /**
     * Settle a QRIS order, on the provider's word.
     *
     * Called by the gateway once Qrisly has confirmed the payment. It carries
     * no body on purpose: there is nothing for a caller to assert. The order is
     * named by its token and the new status is decided here, so the request
     * cannot say how much was paid or what status to write.
     *
     * That is what keeps it safe to hang off a public token. The token gets you
     * a request to this route; what settles the order is Qrisly having said so,
     * which happens on the other side of the gateway and out of reach of
     * anyone holding a receipt link.
     *
     * Idempotent, so a receipt page that polls does not write a history row per
     * tick -- `data.changed` says whether this call was the one that moved it.
     */
    public function qrisPaid(Request $request, $website, $token)
    {
        $trx = $this->transactions->getByToken($token, (int) $website);

        if (!$trx) {
            return $this->notFound('Order');
        }

        return $this->respond(
            $this->transactions->markQrisPaid($trx, $request->header('X-User-Email'))
        );
    }

    /**
     * Customer-facing upload of proof of payment against a placed order.
     *
     * Uploading is also a claim: the order moves from "waiting for payment" to
     * "waiting for confirmation", so it appears in the seller's queue rather
     * than sitting among the unpaid. TransactionService::recordPaymentProof
     * does both as one act, and answers with the status the order ended at --
     * which is not always the new one, since an order already paid keeps what
     * it has.
     */
    public function uploadAttachment(Request $request, $website, $token, UploadService $upload)
    {
        $trx = $this->transactions->getByToken($token, (int) $website);

        if (!$trx) {
            return $this->notFound('Order');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:5120',
            'type' => ['nullable', Rule::in(array_keys(TransactionAttachment::TYPES))],
            'note' => 'nullable|string|max:500',
        ]);

        // Capture file metadata BEFORE storing: UploadService moves the temp
        // file, after which getSize()/getMimeType() would stat a missing path.
        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mime         = $file->getClientMimeType();
        $size         = $file->getSize();

        $up = $upload->store($file, 'transaction');

        if (!$up) {
            return response()->json(['message' => 'Gagal mengunggah berkas. Silakan coba lagi.'], 422);
        }

        $result = $this->transactions->recordPaymentProof($trx, [
            'type'           => $data['type'] ?? TransactionService::PROOF_TYPE,
            'file_path'      => $up['uploaded'],
            'original_name'  => $up['original'] ?? $originalName,
            'mime'           => $mime,
            'size'           => $size,
            'note'           => $data['note'] ?? null,
            'uploaded_by'    => $trx->customer_email ?: 'customer',
        ], $trx->customer_email ?: 'customer');

        if (($result['status'] ?? '') !== 'success') {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data'    => $result['data'],
            // Where the order stands now, so the receipt can show it without
            // re-reading the whole thing.
            'transaction_status' => $result['transaction_status'],
        ], 201);
    }
}
