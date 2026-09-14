<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Exceptions\ValidationException;
use WpsMicro\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testRegisteredNamesTakePriorityOverPhpFunctions(): void
    {
        $validator = new Validator();
        $validator->addRule('is_int', static fn (mixed $value): bool => $value === 'custom');

        self::assertSame(['value' => 'custom'], $validator->validate(['value' => 'custom'], ['value' => 'is_int']));
        self::assertSame(['value' => 'custom'], $validator->validate(['value' => 'custom'], ['value' => ['is_int']]));

        $this->expectException(ValidationException::class);
        $validator->validate(['value' => 42], ['value' => 'is_int']);
    }

    public function testUnregisteredPhpFunctionNamesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule: is_int');

        (new Validator())->validate(['value' => 42], ['value' => 'is_int']);
    }

    public function testInlineCallableFormsStillWork(): void
    {
        $rule = new class () {
            public function __invoke(mixed $value): bool
            {
                return $this->accept($value);
            }

            public function accept(mixed $value): bool
            {
                return $value === 'valid';
            }
        };

        foreach ([$rule, [$rule, 'accept'], [[$rule, 'accept']], [$rule->accept(...)]] as $definition) {
            self::assertSame(['value' => 'valid'], (new Validator())->validate(['value' => 'valid'], ['value' => $definition]));
        }
    }

    #[DataProvider('numericBoundaries')]
    public function testNumericBoundariesPreserveIntegerPrecision(int|string $value, string $rules, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(ValidationException::class);
        }

        self::assertSame(['value' => $value], (new Validator())->validate(['value' => $value], ['value' => $rules]));
    }

    public static function numericBoundaries(): iterable
    {
        yield ['9007199254740993', 'numeric|max:9007199254740992', false];
        yield [9007199254740993, 'integer|max:9007199254740992', false];
        yield ['9007199254740992', 'numeric|min:9007199254740993', false];
        yield ['9007199254740993', 'numeric|min:9007199254740993|max:9007199254740993', true];
        yield ['9223372036854775809', 'numeric|max:9223372036854775808', false];
        yield ['-9223372036854775809', 'numeric|min:-9223372036854775808', false];
        yield ['-9223372036854775808', 'numeric|min:-9223372036854775808', true];
        yield ['+0009007199254740993', 'numeric|max:+0009007199254740992', false];
        yield ['-00042', 'numeric|min:-42|max:-42', true];
        yield ['-0', 'numeric|min:+0|max:000', true];
        yield ['0', 'numeric|min:-0|max:+0', true];
        yield ['-1', 'numeric|min:0', false];
        yield ['1', 'numeric|max:-1', false];
        yield ['10', 'numeric|min:9', true];
        yield ['-10', 'numeric|max:-9', true];
        yield ['1.25', 'numeric|min:1.2|max:1.3', true];
        yield ['1e2', 'numeric|min:99|max:101', true];
    }

    #[DataProvider('nonFiniteNumbers')]
    public function testNumericRejectsNonFiniteValues(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(['value' => $value], ['value' => 'numeric|min:0|max:100']);
    }

    public static function nonFiniteNumbers(): iterable
    {
        yield [NAN];
        yield [INF];
        yield [-INF];
        yield ['NAN'];
        yield ['INF'];
        yield ['1e999'];
        yield [true];
    }

    public function testNonFiniteLimitsAreConfigurationErrors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Validator())->validate(['value' => 1], ['value' => 'numeric|max:1e999']);
    }

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
                $exception->errors(),
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

    public function testMinAndMaxCompareNumericValuesForNumericFields(): void
    {
        $validator = new Validator();

        $validated = $validator->validate([
            'quantity' => '10',
            'price' => '19.95',
        ], [
            'quantity' => 'required|integer|min:1|max:100',
            'price' => 'required|numeric|min:0.01|max:999.99',
        ]);

        self::assertSame(['quantity' => '10', 'price' => '19.95'], $validated);

        try {
            $validator->validate(['quantity' => '101'], [
                'quantity' => 'integer|min:1|max:100',
            ]);
        } catch (ValidationException $exception) {
            self::assertSame([
                'quantity' => ['quantity may not be greater than 100.'],
            ], $exception->errors());

            return;
        }

        self::fail('A numeric value above the maximum was accepted.');
    }

    public function testNumericMinimumRejectsValuesBelowTheLimit(): void
    {
        $validator = new Validator();

        try {
            $validator->validate(['price' => '0'], [
                'price' => 'required|numeric|min:0.01',
            ]);
        } catch (ValidationException $exception) {
            self::assertSame([
                'price' => ['price must be at least 0.01.'],
            ], $exception->errors());

            return;
        }

        self::fail('A numeric value below the minimum was accepted.');
    }

    public function testItSupportsRegisteredAndInlineCustomRules(): void
    {
        $validator = new Validator();
        $validator->addRule(
            'divisible_by',
            static function (mixed $value, string $field, array $data, ?string $parameter): bool|string {
                if ($parameter === null || (int) $parameter === 0) {
                    return 'The divisor is invalid.';
                }

                return (int) $value % (int) $parameter === 0
                    ?: $field.' must be divisible by '.$parameter.'.';
            },
        );

        $validated = $validator->validate([
            'quantity' => '12',
            'sku' => 'WPS-31',
        ], [
            'quantity' => 'required|integer|divisible_by:3',
            'sku' => [
                'required',
                static fn (mixed $value): bool|string => str_starts_with((string) $value, 'WPS-')
                    ?: 'sku must start with WPS-.',
            ],
        ]);

        self::assertSame(['quantity' => '12', 'sku' => 'WPS-31'], $validated);

        try {
            $validator->validate(['quantity' => '10'], [
                'quantity' => 'divisible_by:3',
            ]);
        } catch (ValidationException $exception) {
            self::assertSame([
                'quantity' => ['quantity must be divisible by 3.'],
            ], $exception->errors());

            return;
        }

        self::fail('A value rejected by a custom rule was accepted.');
    }
}
