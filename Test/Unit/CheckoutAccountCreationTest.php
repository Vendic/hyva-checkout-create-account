<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Test\Unit;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendic\HyvaCheckoutCreateAccount\Magewire\Checkbox;
use Vendic\HyvaCheckoutCreateAccount\Magewire\CreateAccount;
use Vendic\HyvaCheckoutCreateAccount\Model\Config;
use Vendic\HyvaCheckoutCreateAccount\Model\EmailSender;
use Vendic\HyvaCheckoutCreateAccount\Observer\ConvertGuestToCustomer;

class CheckoutAccountCreationTest extends TestCase
{
    public function testExistingEmailClearsCheckedSelection(): void
    {
        $session = new SessionStub();
        $checkbox = new Checkbox($session);
        $checkbox->updatedCreateAccount(true);
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->method('get')->with('existing@example.com')->willReturn(
            $this->createMock(CustomerInterface::class)
        );
        $component = $this->getMockBuilder(CreateAccount::class)
            ->setConstructorArgs([$repository, $session])
            ->onlyMethods(['emitTo'])
            ->getMock();
        $component->expects(self::once())->method('emitTo')
            ->with('create-account.checkbox', 'create_account_disabled');

        $component->hideIfAccountExists('existing@example.com');

        self::assertTrue($component->accountExists);
        self::assertFalse($session->getData(Checkbox::CREATE_ACCOUNT_ENABLED));
    }

    public function testCheckboxResetsWhenOptionIsHidden(): void
    {
        $session = new SessionStub();
        $checkbox = new Checkbox($session);
        $checkbox->updatedCreateAccount(true);
        $checkbox->createAccount = true;
        self::assertSame('resetCreateAccount', $checkbox->getListeners()['create_account_disabled'] ?? null);
        $checkbox->resetCreateAccount();
        self::assertFalse($checkbox->createAccount);
        self::assertFalse($session->getData(Checkbox::CREATE_ACCOUNT_ENABLED));
    }

    public function testUnusedEmailDoesNotEnableAccountCreationAgain(): void
    {
        $session = new SessionStub();
        $session->setData(Checkbox::CREATE_ACCOUNT_ENABLED, false);
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $component = new CreateAccount($repository, $session);
        $component->accountExists = true;
        $component->hideIfAccountExists('unused@example.com');
        self::assertFalse($component->accountExists);
        self::assertFalse($session->getData(Checkbox::CREATE_ACCOUNT_ENABLED));
    }

    public function testExistingEmailAtSubmissionRemainsGuestWithoutSaving(): void
    {
        [$subject, $repository, $quote, $order, $logger, $sender] = $this->submission();
        $repository->expects(self::once())->method('get')->with('existing@example.com', 2)
            ->willReturn($this->createMock(CustomerInterface::class));
        $repository->expects(self::never())->method('save');
        $logger->expects(self::never())->method('error');
        $sender->expects(self::never())->method('sendPasswordResetEmail');
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertNull($quote->getCustomerId());
        self::assertSame(1, $quote->getCustomerIsGuest());
        self::assertNull($order->getCustomerId());
    }

    public function testNewEmailCreatesCustomerInQuoteWebsite(): void
    {
        [$subject, $repository, $quote, $order, , $sender] = $this->submission();
        $repository->expects(self::once())->method('get')->with('existing@example.com', 2)
            ->willThrowException(new NoSuchEntityException());
        $repository->expects(self::once())->method('save')->willReturnCallback(
            function (CustomerInterface $customer): CustomerInterface {
                self::assertSame(2, $customer->getWebsiteId());
                return $customer->setId(42);
            }
        );
        $sender->expects(self::once())->method('sendPasswordResetEmail');
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertSame(42, $quote->getCustomerId());
        self::assertSame(0, $quote->getCustomerIsGuest());
        self::assertSame(3, $quote->getCustomerGroupId());
        self::assertSame(42, $order->getCustomerId());
    }

    public function testConcurrentDuplicateDoesNotLogOrAttachExistingCustomer(): void
    {
        [$subject, $repository, $quote, $order, $logger, $sender] = $this->submission();
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $repository->method('save')->willThrowException(new AlreadyExistsException(new Phrase('Duplicate')));
        $logger->expects(self::never())->method('error');
        $sender->expects(self::never())->method('sendPasswordResetEmail');
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertNull($quote->getCustomerId());
        self::assertNull($order->getCustomerId());
        self::assertSame(1, $quote->getCustomerIsGuest());
    }

    public function testOtherSaveFailuresAreStillReported(): void
    {
        [$subject, $repository, $quote, $order, $logger] = $this->submission();
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $repository->method('save')->willThrowException(new \RuntimeException('Storage unavailable'));
        $logger->expects(self::once())->method('error')->with(
            'Could not create customer existing@example.com from quote: Storage unavailable'
        );
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertNull($order->getCustomerId());
    }

    public function testLookupFailuresAreReportedWithoutInterruptingGuestCheckout(): void
    {
        [$subject, $repository, $quote, $order, $logger] = $this->submission();
        $repository->method('get')->willThrowException(new \RuntimeException('Lookup unavailable'));
        $repository->expects(self::never())->method('save');
        $logger->expects(self::once())->method('error')->with(
            'Could not create customer existing@example.com from quote: Lookup unavailable'
        );
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertNull($order->getCustomerId());
        self::assertSame(1, $quote->getCustomerIsGuest());
    }

    public function testAuthenticatedQuoteDoesNotLookUpOrSaveCustomer(): void
    {
        [$subject, $repository, $quote, $order] = $this->submission();
        $quote->setCustomerId(123);
        $repository->expects(self::never())->method('get');
        $repository->expects(self::never())->method('save');
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
        self::assertSame(123, $quote->getCustomerId());
    }

    public function testUncheckedSelectionDoesNotLookUpOrSaveCustomer(): void
    {
        [$subject, $repository, $quote, $order] = $this->submission(false);
        $repository->expects(self::never())->method('get');
        $repository->expects(self::never())->method('save');
        $subject->execute(new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]));
    }

    private function submission(bool $checked = true): array
    {
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $session = new SessionStub();
        $session->setData(Checkbox::CREATE_ACCOUNT_ENABLED, $checked);
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(2);
        $store->method('getId')->willReturn(5);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $currentStore = $this->createMock(Store::class);
        $currentStore->method('getWebsiteId')->willReturn(1);
        $storeManager->method('getStore')->willReturn($currentStore);
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getStore', 'getBillingAddress'])->getMock();
        $quote->method('getStore')->willReturn($store);
        $quote->setStoreId(5)->setCustomerEmail('existing@example.com')->setCustomerIsGuest(1);
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $address->setFirstname('Test')->setLastname('Customer');
        $quote->method('getBillingAddress')->willReturn($address);
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $factory = $this->createMock(CustomerInterfaceFactory::class);
        $customer = $this->getMockBuilder(\Magento\Customer\Model\Data\Customer::class)
            ->disableOriginalConstructor()->onlyMethods([])->getMock();
        $factory->method('create')->willReturn($customer);
        $logger = $this->createMock(LoggerInterface::class);
        $config = $this->createMock(Config::class);
        $config->method('getDefaultCustomerGroup')->willReturn(3);
        $config->method('sendPasswordMailEnabled')->willReturn(true);
        $sender = $this->createMock(EmailSender::class);
        $subject = new ConvertGuestToCustomer(
            $factory,
            $repository,
            $this->createMock(AccountManagementInterface::class),
            $storeManager,
            $session,
            $logger,
            $config,
            $sender
        );
        return [$subject, $repository, $quote, $order, $logger, $sender];
    }
}
