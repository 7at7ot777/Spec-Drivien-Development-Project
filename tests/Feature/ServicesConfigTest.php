<?php

test('sms and fawry services read their environment keys', function () {
    expect(config('services.sms'))->toHaveKeys(['driver', 'url', 'key', 'sender_id'])
        ->and(config('services.fawry'))->toHaveKeys(['base_url', 'merchant_code', 'security_key', 'callback_url']);
});

test('sms driver defaults to log', function () {
    expect(config('services.sms.driver'))->toBe('log');
});
