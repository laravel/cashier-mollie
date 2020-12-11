<?php

namespace Laravel\Cashier\Order;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Cashier\Order\Contracts\InvoicableItem;
use Laravel\Cashier\Traits\FormatsAmount;
use Money\Money;
use Symfony\Component\HttpFoundation\Response;

class Invoice
{
    use FormatsAmount;

    const DEFAULT_VIEW = 'cashier::receipt';

    /**
     * The invoice id. Also know as "reference".
     *
     * @var null|string
     */
    protected $id;

    /**
     * The currency used in this invoice.
     * @example EUR
     * @var string
     */
    protected $currency;

    /**
     * The invoice date.
     *
     * @var Carbon
     */
    protected $date;

    /**
     * Items that should be listed on the invoice.
     *
     * @var Collection A collection of InvoicableItems
     */
    protected $items;

    /**
     * A collection of strings representing lines of the receiver address.
     *
     * @var Collection
     */
    protected $receiverAddress;

    /**
     * The customer starting balance.
     *
     * @var \Money\Money
     */
    protected $startingBalance;

    /**
     * The amount of customer's starting balance applied to the total.
     *
     * @var \Money\Money
     */
    protected $usedBalance;

    /**
     * The amount of customer balance left when this invoice is incurred.
     *
     * @var \Money\Money
     */
    protected $completedBalance;

    /**
     * A collection of strings representing lines of additional information to be added to the invoice.
     * Typically this information is added as a note.
     *
     * @var Collection
     */
    protected $extraInformation;

    /**
     * Invoice constructor.
     *
     * @param string $currency
     * @param string|null $id
     * @param Carbon|null $date
     */
    public function __construct($currency, $id = null, $date = null)
    {
        $this->currency = $currency;
        $this->id = $id;
        $this->date = $date ?: Carbon::now();
        $this->items = new Collection;
        $this->receiverAddress = new Collection;
        $this->extraInformation = new Collection;
    }

    /**
     * @return null|string
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return \Carbon\Carbon
     */
    public function date()
    {
        return $this->date;
    }

    /**
     * @param \Carbon\Carbon $date
     * @return $this
     */
    public function setDate(Carbon $date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @param \Laravel\Cashier\Order\Contracts\InvoicableItem $item
     * @return $this
     */
    public function addItem(InvoicableItem $item)
    {
        $this->items->push($item);

        return $this;
    }

    /**
     * Add multiple InvoicableItems
     *
     * @param \Illuminate\Support\Collection $items
     * @return $this
     */
    public function addItems(Collection $items)
    {
        $this->items = $this->items->concat($items);

        return $this;
    }

    /**
     * Returns a collection of invoice items
     *
     * @return \Illuminate\Support\Collection
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * @return string
     * @example EUR
     */
    public function currency()
    {
        return $this->currency;
    }

    /**
     * The formatted sum of all items' subtotals
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->formatAmount($this->rawSubtotal());
    }

    /**
     * The raw sum of all items' subtotals
     *
     * @return \Money\Money
     */
    public function rawSubtotal()
    {
        $subtotal = money(0, $this->currency);

        $this->items->each(function (InvoicableItem $item) use (&$subtotal) {
            $subtotal = $subtotal->add($item->getSubtotal());
        });

        return $subtotal;
    }

    /**
     * The formatted sum of all items' totals.
     *
     * @return string
     */
    public function total()
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * The raw sum of all items' totals.
     *
     * @return \Money\Money
     */
    public function rawTotal()
    {
        $subtotal = money(0, $this->currency);

        $this->items->each(function (InvoicableItem $item) use (&$subtotal) {
            $subtotal = $subtotal->add($item->getTotal());
        });

        return $subtotal;
    }

    /**
     * The formatted sum of all items' totals minus the balance used.
     *
     * @return string
     */
    public function totalDue()
    {
        return $this->formatAmount($this->rawTotalDue());
    }

    /**
     * The raw sum of all items' totals minus the balance used.
     *
     * @return \Money\Money
     */
    public function rawTotalDue()
    {
        return $this->rawTotal()->subtract($this->rawUsedBalance());
    }

    /**
     * Get the tax totals summed up per tax percentage.
     *
     * @return \Illuminate\Support\Collection
     */
    public function taxDetails()
    {
        $percentages = $this->items->pluck('tax_percentage')->unique()->sort();

        $total_per_percentage = function ($percentage) {
            $items = $this->items->where('tax_percentage', $percentage);

            $raw_over_subtotal = $items->sum(function (InvoicableItem $item) {
                return $item->getSubtotal()->getAmount();
            });

            $raw_total = $items->sum(function (InvoicableItem $item) {
                return $item->getTax()->getAmount();
            });

            return [
                'tax_percentage' => (float) $percentage,
                'raw_over_subtotal' => $raw_over_subtotal,
                'over_subtotal' => $this->formatAmount(money($raw_over_subtotal, $this->currency)),
                'raw_total' => $raw_total,
                'total' => $this->formatAmount(money($raw_total, $this->currency)),
            ];
        };

        return $percentages->map($total_per_percentage)->values();
    }

    /**
     * Get the receiver address. By default a collection of lines (strings) is returned.
     * If you provide separator, an imploded string is returned.
     *
     * @param null $separator
     * @return \Illuminate\Support\Collection|string
     */
    public function receiverAddress($separator = null)
    {
        return $this->optionallyImplode($this->receiverAddress, $separator);
    }

    /**
     * Set the receiver address using an array of strings.
     *
     * @param array $lines
     * @return $this
     */
    public function setReceiverAddress(array $lines)
    {
        $this->receiverAddress = new Collection($lines);

        return $this;
    }

    /**
     * The formatted starting balance.
     *
     * @return string
     */
    public function startingBalance()
    {
        return $this->formatAmount($this->rawStartingBalance());
    }

    /**
     * The raw starting balance.
     *
     * @return \Money\Money|null
     */
    public function rawStartingBalance()
    {
        return $this->startingBalance ?: money(0, $this->currency);
    }

    /**
     * @param \Money\Money $amount
     * @return $this
     */
    public function setStartingBalance(Money $amount)
    {
        $this->startingBalance = $amount;

        return $this;
    }

    /**
     * The formatted used balance.
     *
     * @return string
     */
    public function usedBalance()
    {
        return $this->formatAmount($this->rawUsedBalance());
    }

    /**
     * The raw used balance.
     *
     * @return \Money\Money|null
     */
    public function rawUsedBalance()
    {
        return $this->usedBalance ?: money(0, $this->currency);
    }

    /**
     * @param \Money\Money $amount
     * @return $this
     */
    public function setUsedBalance(Money $amount)
    {
        $this->usedBalance = $amount;

        return $this;
    }

    /**
     * The formatted balance after processing this invoice.
     *
     * @return string
     */
    public function completedBalance()
    {
        return $this->formatAmount($this->rawCompletedBalance());
    }

    /**
     * The formatted balance after processing this invoice.
     *
     * @return \Money\Money|null
     */
    public function rawCompletedBalance()
    {
        return $this->completedBalance ?: money(0, $this->currency);
    }

    /**
     * @param \Money\Money $amount
     * @return $this
     */
    public function setCompletedBalance(Money $amount)
    {
        $this->completedBalance = $amount;

        return $this;
    }

    /**
     * Get the invoice's extra information.
     * By default a collection of lines (strings) is returned.
     * If you provide separator, an imploded string is returned.
     *
     * @param null $separator
     * @return \Illuminate\Support\Collection|string
     */
    public function extraInformation($separator = null)
    {
        return $this->optionallyImplode($this->extraInformation, $separator);
    }

    /**
     * Set the extra information. Useful for adding a note.
     *
     * @param array $lines
     * @return $this
     */
    public function setExtraInformation(array $lines)
    {
        $this->extraInformation = new Collection($lines);

        return $this;
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param array $data
     * @param string $view
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data = [], string $view = self::DEFAULT_VIEW)
    {
        return View::make($view, array_merge($data, [
            'invoice' => $this,
        ]));
    }

    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param array $data
     * @param string $view
     * @param \Dompdf\Options $options
     * @return string
     */
    public function pdf(array $data = [], string $view = self::DEFAULT_VIEW, Options $options = null)
    {
        if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
        }

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->view($data, $view)->render());
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Create an invoice download response.
     *
     * @param null|array $data
     * @param string $view
     * @param \Dompdf\Options $options
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(array $data = [], string $view = self::DEFAULT_VIEW, Options $options = null)
    {
        $filename = implode('_', [
                $this->id,
                Str::snake(config('app.name', '')),
            ]) . '.pdf';

        return new Response($this->pdf($data, $view, $options), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return bool
     */
    public function hasStartingBalance()
    {
        return ! $this->rawStartingBalance()->isZero();
    }

    /**
     * Helper method. By default a collection of lines (strings) is returned.
     * If a separator is provided an imploded string is returned.
     *
     * @param \Illuminate\Support\Collection $collection
     * @param $separator
     * @return \Illuminate\Support\Collection|string
     */
    private function optionallyImplode(Collection $collection, $separator)
    {
        if ($separator === null) {
            return $collection;
        }

        return $collection->implode($separator);
    }
}
