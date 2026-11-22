<?php

namespace Tests\Unit;

use App\Services\TelegramInitDataValidator;
use PHPUnit\Framework\TestCase;

class TelegramInitDataValidatorTest extends TestCase
{
    private const BOT_TOKEN = 'test-bot-token';

    private function buildInitData(array $overrides = []): string
    {
        $params = array_merge([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 123456789, 'first_name' => 'Alex', 'last_name' => 'SY']),
            'start_param' => 'better-b-cafe',
        ], $overrides);

        ksort($params);
        $dataCheckString = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $secretKey = hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($params);
    }

    public function test_a_correctly_signed_payload_validates_and_extracts_the_user(): void
    {
        $validator = new TelegramInitDataValidator();

        $result = $validator->validate($this->buildInitData(), self::BOT_TOKEN);

        $this->assertNotNull($result);
        $this->assertSame('123456789', $result['telegram_user_id']);
        $this->assertSame('Alex', $result['first_name']);
        $this->assertSame('SY', $result['last_name']);
        $this->assertSame('better-b-cafe', $result['start_param']);
    }

    public function test_a_tampered_payload_fails_validation(): void
    {
        $validator = new TelegramInitDataValidator();

        $initData = $this->buildInitData();
        $tampered = str_replace('Alex', 'Mallory', $initData);

        $this->assertNull($validator->validate($tampered, self::BOT_TOKEN));
    }

    public function test_the_wrong_bot_token_fails_validation(): void
    {
        $validator = new TelegramInitDataValidator();

        $result = $validator->validate($this->buildInitData(), 'a-different-bot-token');

        $this->assertNull($result);
    }

    public function test_a_stale_auth_date_fails_validation(): void
    {
        $validator = new TelegramInitDataValidator();

        $initData = $this->buildInitData(['auth_date' => (string) (time() - 100000)]);

        $this->assertNull($validator->validate($initData, self::BOT_TOKEN));
    }

    public function test_a_missing_hash_fails_validation(): void
    {
        $validator = new TelegramInitDataValidator();

        $this->assertNull($validator->validate('auth_date=123&user=%7B%7D', self::BOT_TOKEN));
    }
}
