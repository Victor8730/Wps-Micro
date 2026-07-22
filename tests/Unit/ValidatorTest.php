<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Exceptions\ValidationException;
use WpsMicro\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testItReturnsOnlyValidatedAndTrimmedInput(): void
    {
        $validator = new Validator();

        $validated = $validator->validate([
            'email' => ' victor@example.com ',
            'age' => '42',
            'ignored' => 'value',
        ], [
            'email' => 'required|email',
            'age' => 'required|integer',
        ]);

        self::assertSame([
            'email' => 'victor@example.com',
            'age' => '42',
        ], $validated);
    }

    public function testItReturnsErrorsGroupedByField(): void
    {
        $validator = new Validator();

        try {
            $validator->validate([
                'email' => 'invalid',
                'role' => 'owner',
            ], [
                'email' => 'required|email',
                'role' => 'in:admin,customer',
            ]);
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
            self::assertArrayHasKey('role', $exception->errors());

            return;
        }

        self::fail('Invalid input was accepted.');
    }

    public function testConfirmedRuleAcceptsMatchingValues(): void
    {
        $validator = new Validator();

        $validated = $validator->validate([
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ], [
            'password' => 'required|confirmed',
        ]);

        self::assertSame(['password' => 'secret-pass'], $validated);
    }

    public function testConfirmedRuleRejectsDifferentValues(): void
    {
        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid.');

        $validator->validate([
            'password' => 'secret-pass',
            'password_confirmation' => 'different-pass',
        ], [
            'password' => 'required|confirmed',
        ]);
    }

    public function testItValidatesExplicitInputTypes(): void
    {
        $validator = new Validator();
        $validated = $validator->validate([
            'title' => 'Product',
            'tags' => ['php', 'twig'],
            'active' => '1',
        ], [
            'title' => 'required|string',
            'tags' => 'required|array',
            'active' => 'required|boolean',
        ]);

        self::assertSame([
            'title' => 'Product',
            'tags' => ['php', 'twig'],
            'active' => '1',
        ], $validated);
    }

    public function testItRejectsArraysForStringRulesWithoutWarnings(): void
    {
        $validator = new Validator();

        try {
            $validator->validate([
                'name' => ['unexpected'],
            ], [
                'name' => 'required|string|min:2|max:120',
            ]);
        } catch (ValidationException $exception) {
            self::assertSame(
                ['name' => ['name must be a string.']],
                $exception->errors()
            );

            return;
        }

        self::fail('An array was accepted as a string.');
    }

    public function testRequiredRejectsAnEmptyArray(): void
    {
        $validator = new Validator();

        $this->expectException(ValidationException::class);

        $validator->validate([
            'items' => [],
        ], [
            'items' => 'required|array',
        ]);
    }

    public function testItRejectsUnknownRulesForMissingValues(): void
    {
        $validator = new Validator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule: requried');

        $validator->validate([], [
            'email' => 'requried|email',
        ]);
    }
}
