<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Math\Random;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vendic\HyvaCheckoutCreateAccount\Model\Config;

class EmailSender
{
    private const DEFAULT_STORE_ID = 0;
    private const LOG_PREFIX = '[Vendic_HyvaCheckoutCreateAccount]';

    public function __construct(
        private StoreManagerInterface $storeManager,
        private LoggerInterface $logger,
        private Config $newAccountConfig,
        private SenderResolverInterface $senderResolver,
        private ScopeConfigInterface $scopeConfig,
        private TransportBuilder $transportBuilder,
        private Random $mathRandom,
        private AccountManagementInterface $accountManagement,
    ) {
    }

    public function sendPasswordResetEmail(CustomerInterface $customer): void
    {
        $email = $customer->getEmail();
        $templateId = $this->newAccountConfig->getNewPasswordTemplate();

        if (!$email || !$templateId) {
            $this->logger->error(sprintf('%s No email or template', self::LOG_PREFIX));
            return;
        }

        try {
            $newPasswordToken = $this->mathRandom->getUniqueHash();
            $this->accountManagement->changeResetPasswordLinkToken($customer, $newPasswordToken);

            $resetUrl = $this->getResetPasswordUrl($newPasswordToken, $this->getStoreId(), (int)$customer->getId());

            $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->getStoreId()])
                ->setTemplateVars([
                    'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'reset_password_url' => $resetUrl
                ])
                ->setFromByScope($this->getEmailSenderFrom())
                ->addTo($email)
                ->getTransport()
                ->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('%s Email send failed: %s', self::LOG_PREFIX, $e->getMessage())
            );
        }
    }

    private function getResetPasswordUrl(string $token, int $storeId, int $customerId): string
    {
        return $this->storeManager->getStore($storeId)
            ->getUrl(
                'customer/account/createPassword',
                [
                    '_query' => [
                        'id' => $customerId,
                        'token' => $token
                    ],
                    '_secure' => true,
                    '_nosid' => 1
                ]
            );
    }

    private function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            $this->logger->error(sprintf('%s Store Id failed: %s', self::LOG_PREFIX, $e->getMessage()));
            return self::DEFAULT_STORE_ID;
        }
    }

    private function getEmailSenderFrom(): array
    {
        return $this->senderResolver->resolve(
            $this->scopeConfig->getValue(
                AccountManagement::XML_PATH_FORGOT_EMAIL_IDENTITY,
                ScopeInterface::SCOPE_STORES
            )
        );
    }
}
