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
    public function create()
    {
        $payment = (new FirstPaymentBuilder($this->owner))
            ->setRedirectUrl(config('cashier.update_payment_method.redirect_url'))
            ->setFirstPaymentMethod($this->allowedPaymentMethods())
            ->inOrderTo($this->addToBalanceAction())
            ->create();

        $payment->update();

        return redirect($payment->getCheckoutUrl());
    }

    /**
     * @return array
     */
    protected function allowedPaymentMethods()
    {
        $paymentMethods = $this->owner->subscriptions->map(function ($subscription) {
            if ($subscription->active()) {
                $planModel = app(PlanRepository::class)::findOrFail($subscription->plan);

                return $planModel->paymentMethod();
            }
        })->filter()->unique()->collapse();

        return $paymentMethods->all();
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