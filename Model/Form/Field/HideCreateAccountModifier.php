<?php

/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

declare(strict_types=1);

namespace Vendic\HyvaCheckoutCreateAccount\Model\Form\Field;

use Hyva\Checkout\Magewire\Component\AbstractForm;
use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\AbstractEntityFormModifier;
use Hyva\Checkout\Model\Form\EntityFieldInterface;
use Magewirephp\Magewire\Component;

class HideCreateAccountModifier extends AbstractEntityFormModifier
{
    private const EMAIL_PROPERTY = 'address.email';

    public function apply(AbstractEntityForm $form): AbstractEntityForm
    {
        // Hyvä Checkout 3 address forms, and Hyvä Checkout 1.x forms built on AbstractForm.
        $form->registerModificationListener(
            'hide_create_account_if_shipping_email_exists',
            'form:updated:magewire',
            function (AbstractEntityForm $form, Component $component, string $property, mixed $value) {
                if ($property === self::EMAIL_PROPERTY) {
                    // Magewire 1 and 3 both pass a single non-array argument on as is.
                    // @phpstan-ignore argument.type
                    $component->emit('email_address_updated', (string) $value);
                }

                return $form;
            }
        );

        // The Hyvä Checkout 1.x address form. Checkout 3 still dispatches this deprecated hook, but there the
        // listener above already handled the update.
        $form->registerModificationListener(
            'hide_create_account_if_already_exists',
            'form:shipping:email:updated',
            function (AbstractEntityForm $form, EntityFieldInterface $field, Component $addressComponent) {
                if (!$addressComponent instanceof AbstractForm) {
                    // Magewire 1 and 3 both pass a single non-array argument on as is.
                    // @phpstan-ignore argument.type
                    $addressComponent->emit('email_address_updated', (string) $field->getValue());
                }

                return $form;
            }
        );

        return $form;
    }
}
