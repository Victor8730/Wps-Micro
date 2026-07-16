<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Validator;
use Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

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
}
