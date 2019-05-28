<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Support\Collection;
use Laravel\Cashier\Exceptions\CurrencyMismatchException;

class ActionCollection extends Collection
{
    public function __construct($items = [])
    {
        parent::__construct($items);
        $this->validate();
    }

    protected function validate()
    {
        if($this->isNotEmpty()) {
            $firstAmount = $this->first()->getTotal();
            $this->each(function (BaseAction $item) use ($firstAmount) {
                if(! $item->getTotal()->isSameCurrency($firstAmount))
                    throw new CurrencyMismatchException('All actions must be in the same currency');
            });
        }
    }

    /**
     * @return \Money\Money
     */
    public function total()
    {
        $total = money(0, $this->getCurrency());

        $this->each(function(BaseAction $item) use (&$total) {
            $total = $total->add($item->getTotal());
        });

        return $total;
    }

    /**
     * @return null|string
     */
    public function getCurrency()
    {
        if($this->isNotEmpty()) {
            return $this->first()->getTotal()->getCurrency()->getCode();
        }

        return null;
    }

    /**
     * @return array
     */
    public function toMolliePayload()
    {
        $payload = [];
        foreach ($this->items as $item) {
            $payload[] = $item->getPayload();
        }

        return $payload;
    }
}
