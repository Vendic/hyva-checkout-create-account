<?php

/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

declare(strict_types=1);

namespace Vendic\HyvaCheckoutCreateAccount\Magewire;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magewirephp\Magewire\Component;

class CreateAccount extends Component
{
    private const string ACCOUNT_EXISTS = 'account_exists';

    /**
     * @var bool
     */
    public bool $accountExists = false;

    /**
     * @var array
     */
    protected $listeners = ['email_address_updated' => 'hideIfAccountExists'];

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Session $checkoutSession
    ) {
    }

    public function mount(): void
    {
        $this->accountExists = $this->checkoutSession->getData(self::ACCOUNT_EXISTS) ?? false;
    }

    public function hideIfAccountExists(string $email): void
    {
        try {
            $this->customerRepository->get($email);
            $this->accountExists = true;
            $this->checkoutSession->setData(self::ACCOUNT_EXISTS, true);
            $this->checkoutSession->setData(Checkbox::CREATE_ACCOUNT_ENABLED, false);
            $this->emitTo('create-account.checkbox', 'create_account_disabled');
        } catch (NoSuchEntityException) {
            $this->accountExists = false;
            $this->checkoutSession->setData(self::ACCOUNT_EXISTS, false);
        }
    }
}
