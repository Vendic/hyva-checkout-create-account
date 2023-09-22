<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Magewire;

use Magewirephp\Magewire\Component;
use Magento\Checkout\Model\Session as CheckoutSession;

class Checkbox extends Component
{
    public const CREATE_ACCOUNT_ENABLED = 'create_account_enabled';

    /**
     * @var bool
     */
    public $createAccount;

    public function __construct(private CheckoutSession $checkoutSession)
    {
    }

    public function mount(): void
    {
        $this->createAccount = $this->checkoutSession->getData(self::CREATE_ACCOUNT_ENABLED) ?: false;
    }

    public function updatedCreateAccount(mixed $value) : mixed
    {
        $this->checkoutSession->setData(self::CREATE_ACCOUNT_ENABLED, (bool) $value);

        return $value;
    }
}
