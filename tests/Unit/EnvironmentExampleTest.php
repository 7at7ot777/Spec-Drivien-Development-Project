<?php

/**
 * @return array<string, string>
 */
function envExampleValues(): array
{
    $values = [];

    foreach (file(dirname(__DIR__, 2).'/.env.example', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value);
    }

    return $values;
}

test('env example documents every required variable', function (string $key) {
    expect(envExampleValues())->toHaveKey($key);
})->with([
    'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS',
    'SMS_DRIVER', 'SMS_API_URL', 'SMS_API_KEY', 'SMS_SENDER_ID',
    'FAWRY_BASE_URL', 'FAWRY_MERCHANT_CODE', 'FAWRY_SECURITY_KEY', 'FAWRY_CALLBACK_URL',
    'FILESYSTEM_DISK', 'MEDIA_DISK', 'MEDIA_MAX_VIDEO_MB', 'MEDIA_SIGNED_URL_TTL_MINUTES',
]);

test('env example defaults to mysql', function () {
    expect(envExampleValues()['DB_CONNECTION'])->toBe('mysql');
});

test('env example leaves secrets empty', function (string $key) {
    expect(envExampleValues()[$key])->toBe('');
})->with(['DB_PASSWORD', 'SMS_API_KEY', 'FAWRY_MERCHANT_CODE', 'FAWRY_SECURITY_KEY', 'AWS_SECRET_ACCESS_KEY']);
