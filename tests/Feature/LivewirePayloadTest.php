<?php

use Livewire\Exceptions\PayloadTooLargeException;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

test('Livewire rejects update payloads above the configured 30 MB limit', function () {
    request()->headers->set('Content-Length', (string) (30 * 1024 * 1024 + 1));

    expect(fn () => app(HandleRequests::class)->handleUpdate())
        ->toThrow(PayloadTooLargeException::class);
});
