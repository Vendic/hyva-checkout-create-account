<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Magewire;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magewirephp\Magewire\Component;

class CreateAccount extends Component
{
    private const ACCOUNT_EXISTS = 'account_exists';

    /**
     * @var bool
     */
    public $accountExists = false;

    /**
     * @var array
     */
    public $listeners = ['email_address_updated' => 'hideIfAccountExists'];

    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private Session $checkoutSession
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
        } catch (NoSuchEntityException) {
            $this->accountExists = false;
            $this->checkoutSession->setData(self::ACCOUNT_EXISTS, false);
        }
    }
}
