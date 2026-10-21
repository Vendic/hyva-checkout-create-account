<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Model\Form\Field;

use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\EntityField\AbstractEntityField;
use Hyva\Checkout\Model\Form\EntityForm\GuestDetailsForm;
use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormModifierInterface;
use Magewirephp\Magewire\Component;

/**
 * Hides the create account option when the email in the Guest Details component belongs to an existing account.
 */
class HideCreateAccountGuestDetailsModifier implements EntityFormModifierInterface
{
    private const EMAIL_PROPERTY = 'data.' . GuestDetailsForm::FIELD_EMAIL;

    public function apply(EntityFormInterface $form): EntityFormInterface
    {
        // Send the email as soon as it changes, instead of only when the order is placed. Like Hyvä's guest
        // login, add the email rule: without data-validate, auto-save also sends a partly typed address.
        $form->registerModificationListener(
            'create_account_save_guest_email_on_change',
            'form:init',
            fn (AbstractEntityForm $form) => $form->modifyField(
                GuestDetailsForm::FIELD_EMAIL,
                fn (AbstractEntityField $field) => $field
                    ->replaceAttribute('wire:auto-save', 'wire:auto-save.self')
                    ->setValidationRule('email')
            )
        );

        $form->registerModificationListener(
            'hide_create_account_if_guest_email_exists',
            'form:updated:magewire',
            function (EntityFormInterface $form, Component $component, string $property, mixed $value) {
                if ($property === self::EMAIL_PROPERTY) {
                    $component->emit('email_address_updated', (string) $value);
                }

                return $form;
            }
        );

        return $form;
    }
}
