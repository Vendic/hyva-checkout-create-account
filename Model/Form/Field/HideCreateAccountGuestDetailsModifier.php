<?php

/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

declare(strict_types=1);

namespace Vendic\HyvaCheckoutCreateAccount\Model\Form\Field;

use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\AbstractEntityFormModifier;
use Hyva\Checkout\Model\Form\EntityField\AbstractEntityField;
use Hyva\Checkout\Model\Form\EntityForm\GuestDetailsForm;
use Magewirephp\Magewire\Component;

/**
 * Hides the create account option when the email in the Guest Details component belongs to an existing account.
 */
class HideCreateAccountGuestDetailsModifier extends AbstractEntityFormModifier
{
    private const string EMAIL_PROPERTY = 'data.' . GuestDetailsForm::FIELD_EMAIL;

    public function apply(AbstractEntityForm $form): AbstractEntityForm
    {
        // Send the email as soon as it changes, instead of only when the order is placed. Like Hyvä's guest
        // login, add the email rule: without data-validate, auto-save also sends a partly typed address.
        $form->registerModificationListener(
            'create_account_save_guest_email_on_change',
            'form:init',
            fn (AbstractEntityForm $form) => $form->modifyField(
                GuestDetailsForm::FIELD_EMAIL,
                fn (AbstractEntityField $field) => $this->saveEmailOnChange($field)
            )
        );

        $form->registerModificationListener(
            'hide_create_account_if_guest_email_exists',
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

        return $form;
    }

    private function saveEmailOnChange(AbstractEntityField $field): void
    {
        $field->setValidationRule('email');

        // Hyvä Checkout 1.x saves the email together with the whole form after a delay.
        if ($field->hasAttribute('wire:auto-save')) {
            $field->replaceAttribute('wire:auto-save', 'wire:auto-save.self');
            return;
        }

        // Hyvä Checkout 3 binds the email deferred.
        $field->replaceAttribute('wire:model', 'wire:model.lazy');
    }
}
