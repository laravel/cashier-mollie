<?php


namespace Laravel\Cashier\UpdatePaymentMethod;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\FirstPayment\Actions\AddBalance;
use Laravel\Cashier\FirstPayment\FirstPaymentBuilder;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\UpdatePaymentMethod\Contracts\UpdatePaymentMethodBuilder as Contract;

class UpdatePaymentMethodBuilder implements Contract
{
    /**
     * The billable model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * UpdatePaymentMethodBuilder constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     */
    public function __construct(Model $owner)
    {
        $this->owner = $owner;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function submit()
    {
        $payment = (new FirstPaymentBuilder($this->owner))
            ->setRedirectUrl(config('cashier.update_payment_method.redirect_url'))
            ->setFirstPaymentMethod($this->allowedPaymentMethod())
            ->inOrderTo($this->addToBalanceAction())
            ->create();

        $payment->update();

        return redirect($payment->getCheckoutUrl());
    }

    /**
     * @return string
     */
    protected function allowedPaymentMethod()
    {
        $plan = $this->owner->subscriptions->last()->plan;

        $planModel = app(PlanRepository::class)::findOrFail($plan);

        return $planModel->firstPaymentMethod();
    }

    /**
     * @return \Laravel\Cashier\FirstPayment\Actions\AddBalance[]
     */
    protected function addToBalanceAction()
    {
        return [
            new AddBalance(
                $this->owner,
                mollie_array_to_money(config('cashier.update_payment_method.amount')),
                __("Payment method updated")
            ),
        ];
    }
}
