<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\TransactionStatusRequest;
use App\Models\TransactionAttachment;
use App\Services\Commerce\TransactionService;
use App\Services\Helper\UploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Placed orders, from the seller's side.
 *
 * Read plus two writes: the status, and the files kept against the order.
 * Orders themselves are created by checkout (CheckoutController), never here --
 * an admin moves an order along, it does not invent one.
 */
class TransactionController extends ApiController
{
    public $service;

    public function __construct(TransactionService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Transaction');
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                // What the status may be moved to.
                'statuses' => $this->service->statusOptions(),
                // Slug => label, so the upload form does not hard-code them.
                'attachment_types' => TransactionAttachment::TYPES,
            ],
        ]);
    }

    public function updateStatus(TransactionStatusRequest $request, $id)
    {
        // Ensure the transaction belongs to the caller's website.
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Transaction');
        }

        $result = $this->service->updateStatus(
            $id,
            $request->input('status'),
            $request->input('description'),
            acting_user_email()
        );

        return $this->respond($result);
    }

    /**
     * Keep a file against an order: proof of payment, a photo of the package,
     * a signed delivery note.
     *
     * The customer can already upload against their receipt token
     * (OrderController::uploadAttachment). This is the same store from the
     * seller's side, and the two differ in who is recorded as having uploaded
     * -- so a customer's payment slip can be told apart from a courier note
     * someone here added.
     */
    public function uploadAttachment(Request $request, $id, UploadService $upload)
    {
        // Scoped like every other read here: an order belonging to another
        // website is not found rather than forbidden.
        $trx = $this->service->getRow($id, admin_website_id());

        if (!$trx) {
            return $this->notFound('Transaction');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:5120',
            'type' => ['required', Rule::in(array_keys(TransactionAttachment::TYPES))],
            'note' => 'nullable|string|max:500',
        ]);

        // Read the metadata BEFORE storing: UploadService moves the temp file,
        // after which getSize()/getMimeType() would stat a path that is gone.
        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mime         = $file->getClientMimeType();
        $size         = $file->getSize();

        $up = $upload->store($file, 'transaction');

        if (!$up) {
            return response()->json(['message' => 'Gagal mengunggah berkas. Silakan coba lagi.'], 422);
        }

        $attachment = $this->service->addAttachment($trx->id, [
            'type'           => $data['type'],
            'file_path'      => $up['uploaded'],
            'original_name'  => $up['original'] ?? $originalName,
            'mime'           => $mime,
            'size'           => $size,
            'note'           => $data['note'] ?? null,
            'uploaded_by'    => acting_user_email(),
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully',
            'data'    => $attachment,
        ], 201);
    }
}
