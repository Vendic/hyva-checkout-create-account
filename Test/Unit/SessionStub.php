<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Test\Unit;

use Magento\Checkout\Model\Session;

class SessionStub extends Session
{
    private array $values = [];

    public function __construct()
    {
    }

    public function getData($key = '', $clear = false)
    {
        return $this->values[$key] ?? null;
    }

    public function setData($key, $value = null)
    {
        $this->values[$key] = $value;
        return $this;
    }
}
