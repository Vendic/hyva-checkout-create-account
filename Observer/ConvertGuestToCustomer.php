<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Observer;

use Exception;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;
use Vendic\HyvaCheckoutCreateAccount\Magewire\Checkbox;

class ConvertGuestToCustomer implements ObserverInterface
{
    public function __construct(
        private CustomerInterfaceFactory $customerFactory,
        private CustomerRepositoryInterface $customerRepository,
        private AccountManagementInterface $accountManagement,
        private StoreManagerInterface $storeManager,
        private CheckoutSession $checkoutSession,
        private LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        // Return early if customer already exists or there's no email.
        if ($quote->getCustomerId() || !$quote->getCustomerEmail()) {
            return;
        }

        if (!$this->isCreateAccountChecked()) {
            return;
        }

        $customer = $this->createNewCustomerFromQuote($quote);

        if (!$customer) {
            return;
        }

        // Attach the new customer to the quote and hence to the order.
        $quote->setCustomerId($customer->getId())
            ->setCustomerIsGuest(0)
            // Ideally we should make this configurable.
            ->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);

        $this->sendPasswordResetEmail($customer->getEmail());
    }

    private function createNewCustomerFromQuote(Quote $quote): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $customer = $this->customerFactory->create();
        $email = $quote->getCustomerEmail();

        $customer->setWebsiteId($this->storeManager->getStore()->getWebsiteId())
            ->setEmail($email)
            ->setFirstname($quote->getBillingAddress()->getFirstname())
            ->setLastname($quote->getBillingAddress()->getLastname());

        try {
            return $this->customerRepository->save($customer);
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Could not create customer %s from quote: %s', $email, $e->getMessage())
            );
            return null;
        }
    }

    private function sendPasswordResetEmail(string $email): void
    {
        try {
            $this->accountManagement->initiatePasswordReset(
                $email,
                AccountManagement::EMAIL_RESET,
                $this->storeManager->getStore()->getWebsiteId()
            );
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Could not send password reset email to %s: %s', $email, $e->getMessage())
            );
        }
    }

    private function isCreateAccountChecked(): bool
    {
        return $this->checkoutSession->getData(Checkbox::CREATE_ACCOUNT_ENABLED) ?: false;
    }
}
