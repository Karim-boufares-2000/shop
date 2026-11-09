<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Translator;
use App\Validation\PhoneNumber;
use App\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private function validator(string $locale = 'fr'): Validator
    {
        return new Validator(
            new Translator(dirname(__DIR__, 2) . '/lang', $locale),
            new PhoneNumber('213', ['/^0[567]\d{8}$/']),
        );
    }

    public function testRequiredFieldMissing(): void
    {
        $result = $this->validator()->validate([], ['first_name' => 'required|string']);

        $this->assertTrue($result->fails());
        $this->assertNotNull($result->first('first_name'));
    }

    public function testOptionalEmptyFieldIsNullAndValid(): void
    {
        $result = $this->validator()->validate(['note' => '  '], ['note' => 'nullable|string|max:10']);

        $this->assertFalse($result->fails());
        $this->assertNull($result->validated()['note']);
    }

    public function testLengthIsMeasuredInCharactersNotBytes(): void
    {
        $result = $this->validator()->validate(['name' => 'محمد'], ['name' => 'required|string|min:4|max:4']);

        $this->assertFalse($result->fails());
    }

    public function testMaxLengthFails(): void
    {
        $result = $this->validator()->validate(['name' => 'abcdef'], ['name' => 'required|max:5']);

        $this->assertTrue($result->fails());
    }

    public function testIntRuleCastsAndRejectsDecimals(): void
    {
        $ok = $this->validator()->validate(['qty' => '3'], ['qty' => 'required|int|min_value:1']);
        $bad = $this->validator()->validate(['qty' => '3.5'], ['qty' => 'required|int']);

        $this->assertSame(3, $ok->validated()['qty']);
        $this->assertTrue($bad->fails());
    }

    public function testMinValueRejectsZeroAndNegative(): void
    {
        $this->assertTrue($this->validator()->validate(['qty' => '0'], ['qty' => 'required|int|min_value:1'])->fails());
        $this->assertTrue($this->validator()->validate(['qty' => '-2'], ['qty' => 'required|int|min_value:1'])->fails());
    }

    public function testMoneyRuleNormalizesAndRejects(): void
    {
        $ok = $this->validator()->validate(['price' => '1 500,5'], ['price' => 'required|money']);
        $this->assertSame('1500.50', $ok->validated()['price']);

        foreach (['abc', '-5', '1,500', '12345678901'] as $bad) {
            $this->assertTrue($this->validator()->validate(['price' => $bad], ['price' => 'required|money'])->fails(), $bad);
        }
        $this->assertNull($this->validator()->validate(['price' => ''], ['price' => 'nullable|money'])->validated()['price']);
    }

    public function testFirstErrorsReturnsOneMessagePerField(): void
    {
        $result = $this->validator()->validate([], ['a' => 'required', 'b' => 'required']);

        $this->assertSame(['a', 'b'], array_keys($result->firstErrors()));
        $this->assertIsString($result->firstErrors()['a']);
    }

    public function testPhoneIsNormalized(): void
    {
        $result = $this->validator()->validate(['phone' => '+213 555 12 34 56'], ['phone' => 'required|phone']);

        $this->assertSame('0555123456', $result->validated()['phone']);
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $this->assertTrue($this->validator()->validate(['phone' => '123'], ['phone' => 'required|phone'])->fails());
    }

    public function testControlCharactersAreRejectedInStrings(): void
    {
        $result = $this->validator()->validate(['address' => "rue\x00 x"], ['address' => 'required|string']);

        $this->assertTrue($result->fails());
    }

    public function testArrayInputIsRejected(): void
    {
        $result = $this->validator()->validate(['name' => ['a']], ['name' => 'required|string']);

        $this->assertTrue($result->fails());
    }

    public function testInRuleIsStrict(): void
    {
        $rules = ['type' => 'required|in:home,office'];

        $this->assertFalse($this->validator()->validate(['type' => 'home'], $rules)->fails());
        $this->assertTrue($this->validator()->validate(['type' => 'HOME'], $rules)->fails());
        $this->assertTrue($this->validator()->validate(['type' => 'x'], $rules)->fails());
    }

    public function testClosureRule(): void
    {
        $rule = static fn (string $v): ?string => $v === 'ok' ? null : 'custom error';
        $result = $this->validator()->validate(['f' => 'nope'], ['f' => ['required', $rule]]);

        $this->assertSame('custom error', $result->first('f'));
    }

    public function testErrorMessagesAreTranslated(): void
    {
        $fr = $this->validator('fr')->validate([], ['f' => 'required'])->first('f');
        $ar = $this->validator('ar')->validate([], ['f' => 'required'])->first('f');

        $this->assertNotSame($fr, $ar);
        $this->assertStringNotContainsString('validation.', (string) $ar);
    }

    public function testUnknownRuleFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator()->validate(['f' => 'x'], ['f' => 'required|inconnue']);
    }
}
