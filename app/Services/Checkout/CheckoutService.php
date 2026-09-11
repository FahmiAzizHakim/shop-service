<?php

namespace App\Services\Checkout;

use App\Repositories\Commerce\OtherChargeRepository;
use App\Repositories\Commerce\TransactionRepository;
use App\Repositories\Reference\RegionRepository;
use App\Services\Commerce\CartService;
use App\Services\Commerce\DeliveryPriceService;
use App\Services\Commerce\TransactionService;
use Illuminate\Validation\Rule;

/**
 * Turning a basket into a priced, placed order.
 *
 * Prices are never taken from the request -- only the keys and quantities are.
 * build() re-reads every line from the catalogue and re-resolves the delivery
 * fare from the address, so confirm and place always agree and neither can be
 * talked into a discount by its caller.
 *
 * The header formula the transactions table stores:
 *
 *   total = price - discount + delivery_fee + charges
 *
 * where price is gross (packages at full price) and discount is the sum of the
 * package discounts.
 */
class CheckoutService
{
    /**
     * How the customer pays, and the codes.code each choice is stored as.
     *
     * The request names the method in words; transactions.payment_type holds
     * the code (parentcode 'TRT'), which is what makes a receipt able to print
     * its name. Mapped here rather than sent by the browser, so a caller
     * cannot write an arbitrary code into the column.
     *
     *   manual  transfer to one of the bank accounts, buyer uploads the proof
     *   qris    scan the site's QRIS; carries the admin fee below
     */
    public const METHODS = [
        'manual' => 'TRTMN',
        'qris'   => 'TRTQR',
    ];

    protected $carts;
    protected $delivery;
    protected $transactions;
    protected $orders;
    protected $regions;
    protected $charges;

    public function __construct(
        CartService $carts,
        DeliveryPriceService $delivery,
        TransactionService $transactions,
        TransactionRepository $orders,
        RegionRepository $regions,
        OtherChargeRepository $charges
    ) {
        $this->carts        = $carts;
        $this->delivery     = $delivery;
        $this->transactions = $transactions;
        $this->orders       = $orders;
        $this->regions      = $regions;
        $this->charges      = $charges;
    }

    /**
     * Validation rules shared by confirm and place. The region codes are
     * checked against each other, so a district of another city is rejected.
     *
     * @return array<string, mixed>
     */
    public function rules($request): array
    {
        return [
            'cart_json'        => 'required|string',
            'province_code'    => ['nullable', 'string'],
            'city_code'        => ['required', 'string', Rule::exists('glb_cities', 'city_code')],
            'district_code'    => ['required', 'string', Rule::exists('glb_districts', 'district_code')
                ->where(fn ($q) => $q->where('city_code', $request->input('city_code')))],
            'subdistrict_code' => ['nullable', 'string', Rule::exists('glb_subdistricts', 'subdistrict_code')
                ->where(fn ($q) => $q->where('district_code', $request->input('district_code')))],
            'address_detail'   => 'required|string|max:1000',
            'recipient_name'   => 'required|string|max:191',
            'phone'            => 'required|string|max:30',
            'email'            => 'required|email|max:191',
            // How they intend to pay. It changes what is charged -- QRIS adds
            // the admin fee -- so it is required rather than defaulted: an
            // order whose total depends on it should not be placed on a guess.
            'payment_method'   => ['required', Rule::in(array_keys(self::METHODS))],
        ];
    }

    /**
     * Rebuild the basket from product/variant/package ids, re-price it, resolve
     * the delivery fare and expand the address names.
     *
     * Returns the priced order, or an error message string when the basket
     * cannot be turned into one.
     *
     * @return array|string
     */
    public function build(int $websiteId, array $data)
    {
        $lines = json_decode($data['cart_json'], true);

        if (!is_array($lines) || empty($lines)) {
            return 'Keranjang kosong.';
        }

        // CartService is the single place a cart key becomes a price, so a
        // saved cart and the order built from it cannot disagree.
        $rows = $this->carts->resolveItems($websiteId, $lines);

        $items       = [];   // display rows (the confirmation step)
        $detailRows  = [];   // transaction_details
        $packageRows = [];   // transaction_packages
        $chargeRows  = [];   // transaction_charges
        $benefitRows = [];   // transaction_benefits

        $subtotal = 0;       // gross: package prices before discount + products
        $discount = 0;       // sum of the package discounts

        foreach ($rows as $row) {
            $qty = (int) $row['qty'];

            /* ---- Package: priced in full here, its contents exploded at 0 ---- */
            if ($row['kind'] === 'package') {
                $package = $row['package'];

                $gross     = (float) $row['price'];
                $lineDisc  = (float) $row['discount'] * $qty;
                $lineTotal = ($gross * $qty) - $lineDisc;

                $subtotal += $gross * $qty;
                $discount += $lineDisc;

                $packageRows[] = [
                    'package_id'   => $package->id,
                    'package_code' => $package->package_code,
                    'package_name' => $package->package_name,
                    'qty'          => $qty,
                    'price'        => $gross,
                    'discount'     => $lineDisc,
                    'subtotal'     => $lineTotal,
                ];

                // Marks every exploded row as belonging to this package.
                $note     = 'Termasuk paket: ' . $package->package_name;
                $included = [];

                foreach ($package->details as $d) {
                    $rowQty = max(1, (int) $d->qty) * $qty;
                    $label  = $d->line_label;

                    $included[] = ($rowQty > 1 ? $rowQty . 'x ' : '') . $label;

                    if ($d->line_type === 'product') {
                        $detailRows[] = [
                            'product_id'         => $d->product_id,
                            'product_variant_id' => null,
                            'product_code'       => optional($d->product)->products_code,
                            'product_name'       => $label,
                            'variant_name'       => null,
                            'qty'                => $rowQty,
                            'price'              => 0,
                            'discount'           => 0,
                            'subtotal'           => 0,
                            'remark'             => $note,
                        ];
                    } elseif ($d->line_type === 'charge') {
                        $chargeRows[] = [
                            'other_charge_id' => $d->other_charge_id,
                            'code'            => optional($d->otherCharge)->code,
                            'name'            => $label,
                            'amount'          => 0,
                            'remark'          => $note,
                        ];
                    } else {
                        $benefitRows[] = [
                            'package_id'   => $package->id,
                            'package_name' => $package->package_name,
                            'benefit_name' => $label,
                            'qty'          => $rowQty,
                            'price'        => 0,
                            'discount'     => 0,
                            'subtotal'     => 0,
                            'remark'       => $note,
                        ];
                    }
                }

                $items[] = [
                    'kind'     => 'package',
                    'name'     => $package->package_name,
                    'code'     => $package->package_code,
                    'included' => $included,
                    'price'    => $gross,
                    'qty'      => $qty,
                    'discount' => $lineDisc,
                    'total'    => $lineTotal,
                ];

                continue;
            }

            /* ---- Product / variant / bare service ---- */
            $product = $row['product'];
            $variant = $row['variant'];

            $price     = (float) $row['price'];
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            $detailRows[] = [
                'product_id'         => optional($product)->id,
                'product_variant_id' => optional($variant)->id,
                'product_code'       => optional($product)->products_code,
                'product_name'       => $product ? $product->products_name : optional($row['service'])->service_name,
                'variant_name'       => optional($variant)->variant_name,
                'qty'                => $qty,
                'price'              => $price,
                'discount'           => 0,
                'subtotal'           => $lineTotal,
            ];

            $items[] = [
                'kind'     => 'product',
                'name'     => $row['name'],
                'code'     => optional($product)->products_code,
                'included' => [],
                'price'    => $price,
                'qty'      => $qty,
                'discount' => 0,
                'total'    => $lineTotal,
            ];
        }

        if (empty($items)) {
            return 'Produk tidak ditemukan. Silakan pilih ulang.';
        }

        // Authoritative fare from the address (subdistrict -> district -> city).
        $fareRow = $this->delivery->resolveFare(
            $websiteId,
            $data['city_code'],
            $data['district_code'] ?? null,
            $data['subdistrict_code'] ?? null
        );

        // An address nobody has priced is a refusal, not a free delivery.
        //
        // delivery_prices is the only source of the fare -- the courier APIs in
        // thirdparty-service are not wired into checkout yet -- so a missing
        // row means the cost is unknown, and treating unknown as zero is how an
        // order ships at our expense. Refused here rather than at the form,
        // because place() re-prices from scratch and has to reach the same
        // answer as confirm().
        if (!$fareRow) {
            return 'Ongkos kirim untuk alamat ini belum tersedia. Silakan hubungi kami.';
        }

        $fare = (float) $fareRow->price;

        $address = $this->address($data);

        $method = $data['payment_method'];

        // Paying by QRIS costs the seller a fee, so the order carries one. A
        // charge row rather than a line added to the total: it reaches
        // transaction_charges by name, shows on the receipt, and is summed
        // into transactions.charges like every other charge.
        if ($method === 'qris' && $fee = $this->qrisFee($websiteId)) {
            $chargeRows[] = $fee;
        }

        // Charges that ride inside a package are 0, so this is the QRIS fee
        // alone today; summed so any priced charge reaches transactions.charges.
        $chargeTotal = array_sum(array_column($chargeRows, 'amount'));

        return [
            'items'       => $items,        // display
            'details'     => $detailRows,   // transaction_details
            'packages'    => $packageRows,  // transaction_packages
            'charges'     => $chargeRows,   // transaction_charges
            'benefits'    => $benefitRows,  // transaction_benefits
            'subtotal'    => $subtotal,
            'discount'    => $discount,
            'chargeTotal' => $chargeTotal,
            'fare'        => $fare,
            'fareRow'     => $fareRow,
            'total'       => $subtotal - $discount + $fare + $chargeTotal,
            'address'     => $address,
            'method'      => $method,                  // 'manual' | 'qris'
            'paymentCode' => self::METHODS[$method],   // what the column stores
        ];
    }

    /**
     * The QRIS admin fee as a transaction_charges row, or null when there is
     * nothing to charge.
     *
     * The website's own other_charges row wins, which is how an admin changes
     * the fee for one site or switches it off by deactivating it; the config
     * default applies when no such row exists, so a fresh site still charges
     * the fee instead of silently absorbing it. See config/checkout.php.
     *
     * other_charge_id is left null on the fallback: transaction_charges allows
     * it, and pointing at a master row that does not exist would be worse than
     * saying there was none.
     *
     * @return array|null
     */
    protected function qrisFee(int $websiteId): ?array
    {
        $config = config('checkout.qris_fee');
        $master = $this->charges->activeByCodeForWebsite($config['code'], $websiteId);

        $amount = (float) ($master ? $master->price : $config['amount']);

        if ($amount <= 0) {
            return null;
        }

        return [
            'other_charge_id' => $master->id ?? null,
            'code'            => $master->code ?? $config['code'],
            'name'            => $master->name ?? $config['name'],
            'amount'          => $amount,
            'remark'          => 'Pembayaran QRIS',
        ];
    }

    /**
     * Persist a built order and empty the basket it came from.
     *
     * @param  array  $order  the result of build()
     */
    public function place(int $websiteId, array $order): array
    {
        $subtotal = $order['subtotal'];
        $discount = $order['discount'];
        $charges  = $order['chargeTotal'];
        $fare     = $order['fare'] ?? 0;
        $total    = $subtotal - $discount + $fare + $charges;

        $buyer = $order['address']['email'];

        $header = [
            'website_id'       => $websiteId,
            'transaction_date' => now()->toDateString(),
            'customer_name'    => $order['address']['recipient_name'],
            'customer_email'   => $buyer,
            'customer_phone'   => $order['address']['phone'],
            'price'            => $subtotal,
            'discount'         => $discount,
            'delivery_fee'     => $fare,
            'charges'          => $charges,
            'total'            => $total,
            'tax'              => 0,
            'grandtotal'       => $total,
            // codes.code under 'TRT'. Built from the request's payment_method
            // by build(), never taken from the request itself.
            'payment_type'     => $order['paymentCode'] ?? null,
            'status'           => 'STSPY', // Menunggu Pembayaran
            'created_by'       => $buyer,
            'hist_description' => 'Pesanan dibuat melalui checkout',
        ];

        // Stamp the buyer on every row set the service writes.
        $stamp = fn (array $rows) => array_map(fn ($row) => $row + ['created_by' => $buyer], $rows);

        $address = [
            'recipient_name'   => $order['address']['recipient_name'],
            'recipient_phone'  => $order['address']['phone'],
            'province_code'    => $order['address']['province_code'],
            'city_code'        => $order['address']['city_code'],
            'district_code'    => $order['address']['district_code'],
            'subdistrict_code' => $order['address']['subdistrict_code'],
            'province_name'    => $order['address']['province'],
            'city_name'        => $order['address']['city'],
            'district_name'    => $order['address']['district'],
            'subdistrict_name' => $order['address']['subdistrict'],
            'address_detail'   => $order['address']['detail'],
            'delivery_fee'     => $fare,
        ];

        $result = $this->transactions->store(
            $header,
            $stamp($order['details']),   // products, package contents at 0
            $address,
            $stamp($order['charges']),   // other charges, 0 inside a package
            $stamp($order['packages']),  // packages, at full price + discount
            $stamp($order['benefits'])   // perks, always 0
        );

        if ($result['status'] === 'success') {
            // Ordered, so the saved cart has done its job. A failure leaves it
            // alone deliberately: the buyer can retry from it.
            $this->carts->clear($websiteId);
        }

        return $result;
    }

    /**
     * A customer's orders, matched on email plus the last 4 digits of the
     * phone. Lightweight verification, not a login: both must match.
     */
    public function ordersFor(int $websiteId, string $email, string $phoneLast4)
    {
        return $this->orders->forCustomer($websiteId, $email, $phoneLast4);
    }

    /**
     * The submitted address with each region code expanded to its name.
     */
    protected function address(array $data): array
    {
        return [
            'recipient_name'   => $data['recipient_name'],
            'phone'            => $data['phone'],
            'email'            => $data['email'],
            'detail'           => $data['address_detail'],
            'province_code'    => $data['province_code'] ?? null,
            'city_code'        => $data['city_code'],
            'district_code'    => $data['district_code'] ?? null,
            'subdistrict_code' => $data['subdistrict_code'] ?? null,
            'province'         => $this->regions->provinceName($data['province_code'] ?? null),
            'city'             => $this->regions->cityName($data['city_code']),
            'district'         => $this->regions->districtName($data['district_code'] ?? null),
            'subdistrict'      => $this->regions->subdistrictName($data['subdistrict_code'] ?? null),
        ];
    }
}
