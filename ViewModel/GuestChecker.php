<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class GuestChecker implements ArgumentInterface
{
    public function __construct(private CheckoutSession $checkoutSession)
    {
    }

    public function isCustomerGuest() : bool
    {
        return (bool) $this->checkoutSession->getQuote()->getCustomerIsGuest();
    }
}
