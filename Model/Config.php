<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const NEW_PASSWORD_TEMPLATE = 'hyva_themes_checkout/new_customer/create_password_template';

    public function __construct(private ScopeConfigInterface $scopeConfig)
    {
    }

    public function getNewPasswordTemplate(): string
    {
        return $this->scopeConfig->getValue(
            self::NEW_PASSWORD_TEMPLATE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
