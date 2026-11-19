<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Test\Unit;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Math\Random;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendic\HyvaCheckoutCreateAccount\Model\Config;
use Vendic\HyvaCheckoutCreateAccount\Model\EmailSender;

class EmailSenderTest extends TestCase
{
    public function testSenderIdentityIsReadFromTheCurrentStore(): void
    {
        $storeId = 5;
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getUrl')->willReturn('https://example.com/customer/account/createPassword');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())->method('getValue')
            ->with(AccountManagement::XML_PATH_FORGOT_EMAIL_IDENTITY, ScopeInterface::SCOPE_STORES, $storeId)
            ->willReturn('support');

        $senderResolver = $this->createMock(SenderResolverInterface::class);
        $senderResolver->expects(self::once())->method('resolve')
            ->with('support', $storeId)
            ->willReturn(['email' => 'support@example.com', 'name' => 'Support']);

        $config = $this->createMock(Config::class);
        $config->method('getNewPasswordTemplate')->willReturn('template_id');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('sendMessage');
        $transportBuilder = $this->createMock(TransportBuilder::class);
        $transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $transportBuilder->method('setTemplateVars')->willReturnSelf();
        $transportBuilder->method('setFromByScope')->willReturnSelf();
        $transportBuilder->method('addTo')->willReturnSelf();
        $transportBuilder->method('getTransport')->willReturn($transport);

        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturn('token');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('customer@example.com');
        $customer->method('getId')->willReturn(10);

        (new EmailSender(
            $storeManager,
            $this->createMock(LoggerInterface::class),
            $config,
            $senderResolver,
            $scopeConfig,
            $transportBuilder,
            $random,
            $this->createMock(AccountManagement::class)
        ))->sendPasswordResetEmail($customer);
    }
}
