<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const IS_ENABLED = 'hyva_themes_checkout/new_customer/enable';
    private const NEW_PASSWORD_TEMPLATE = 'hyva_themes_checkout/new_customer/create_password_template';

    public function __construct(private ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->getValue(
            self::IS_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getNewPasswordTemplate(): string
    {
        return $this->scopeConfig->getValue(
            self::NEW_PASSWORD_TEMPLATE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
