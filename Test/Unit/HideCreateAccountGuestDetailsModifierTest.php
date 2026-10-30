<?php declare(strict_types=1);
/**
 * @copyright   Copyright (c) Vendic B.V https://vendic.nl/
 */

namespace Vendic\HyvaCheckoutCreateAccount\Test\Unit;

use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\EntityField\AbstractEntityField;
use Magewirephp\Magewire\Component;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vendic\HyvaCheckoutCreateAccount\Model\Form\Field\HideCreateAccountGuestDetailsModifier;

class HideCreateAccountGuestDetailsModifierTest extends TestCase
{
    private AbstractEntityForm&MockObject $form;
    private array $listeners = [];

    protected function setUp(): void
    {
        $this->form = $this->createMock(AbstractEntityForm::class);
        $this->form->method('registerModificationListener')->willReturnCallback(
            function (string $name, string $event, callable $listener): AbstractEntityForm {
                $this->listeners[$event] = $listener;
                return $this->form;
            }
        );
        (new HideCreateAccountGuestDetailsModifier())->apply($this->form);
    }

    public function testGuestEmailIsSavedAsSoonAsAValidEmailIsEnteredOnCheckout1(): void
    {
        $field = $this->createMock(AbstractEntityField::class);
        $field->method('hasAttribute')->with('wire:auto-save')->willReturn(true);
        $field->expects(self::once())->method('replaceAttribute')
            ->with('wire:auto-save', 'wire:auto-save.self')->willReturnSelf();
        // Without a validation rule Hyvä also saves a partly typed email address.
        $field->expects(self::once())->method('setValidationRule')->with('email')->willReturnSelf();

        $this->initForm($field);
    }

    public function testGuestEmailIsSavedWhenItChangesOnCheckout3(): void
    {
        // Hyvä Checkout 3 has no auto-save, it binds the email deferred.
        $field = $this->createMock(AbstractEntityField::class);
        $field->method('hasAttribute')->with('wire:auto-save')->willReturn(false);
        $field->expects(self::once())->method('replaceAttribute')
            ->with('wire:model', 'wire:model.lazy')->willReturnSelf();
        $field->expects(self::once())->method('setValidationRule')->with('email')->willReturnSelf();

        $this->initForm($field);
    }

    public function testGuestEmailUpdateLetsCreateAccountCheckTheEmail(): void
    {
        $component = $this->createMock(Component::class);
        $component->expects(self::once())->method('emit')
            ->with('email_address_updated', 'existing@example.com');

        // Hyvä passes the property and value as named arguments.
        $this->listeners['form:updated:magewire'](
            $this->form,
            $component,
            property: 'data.email_address',
            value: 'existing@example.com'
        );
    }

    public function testOtherGuestDetailsUpdatesAreIgnored(): void
    {
        $component = $this->createMock(Component::class);
        $component->expects(self::never())->method('emit');

        $this->listeners['form:updated:magewire'](
            $this->form,
            $component,
            property: 'data.confirm_email',
            value: 'existing@example.com'
        );
    }

    private function initForm(AbstractEntityField $field): void
    {
        $this->form->expects(self::once())->method('modifyField')->willReturnCallback(
            function (string $fieldId, callable $modifier) use ($field): AbstractEntityForm {
                self::assertSame('email_address', $fieldId);
                $modifier($field);
                return $this->form;
            }
        );

        $this->listeners['form:init']($this->form);
    }
}
