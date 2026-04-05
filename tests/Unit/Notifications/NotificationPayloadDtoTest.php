<?php

namespace Tests\Unit\Notifications;

use App\NotificationCenter\NotificationPayloadDto;
use Tests\TestCase;

class NotificationPayloadDtoTest extends TestCase
{
    public function test_from_stored_array_trims_empty_action_url_and_label_to_null(): void
    {
        $dto = NotificationPayloadDto::fromStoredArray([
            'title' => 'T',
            'body' => 'B',
            'action_url' => '  ',
            'action_label' => '  ',
            'meta' => [],
        ]);

        $this->assertNull($dto->actionUrl);
        $this->assertNull($dto->actionLabel);
    }

    public function test_from_stored_array_clears_label_when_url_missing(): void
    {
        $dto = NotificationPayloadDto::fromStoredArray([
            'title' => 'T',
            'body' => 'B',
            'action_label' => 'Open',
            'meta' => [],
        ]);

        $this->assertNull($dto->actionUrl);
        $this->assertNull($dto->actionLabel);
    }

    public function test_from_validated_array_normalizes_action_fields(): void
    {
        $dto = NotificationPayloadDto::fromValidatedArray([
            'title' => 'T',
            'body' => 'B',
            'action_url' => '',
            'action_label' => 'Open',
            'meta' => [],
        ]);

        $this->assertNull($dto->actionUrl);
        $this->assertNull($dto->actionLabel);
    }

    public function test_from_validated_array_keeps_label_when_url_present(): void
    {
        $dto = NotificationPayloadDto::fromValidatedArray([
            'title' => 'T',
            'body' => 'B',
            'action_url' => 'https://example.test/x',
            'action_label' => 'Go',
            'meta' => [],
        ]);

        $this->assertSame('https://example.test/x', $dto->actionUrl);
        $this->assertSame('Go', $dto->actionLabel);
    }

    public function test_to_array_serializes_null_actions(): void
    {
        $dto = new NotificationPayloadDto('T', 'B', null, null, []);

        $this->assertSame([
            'title' => 'T',
            'body' => 'B',
            'action_url' => null,
            'action_label' => null,
            'meta' => [],
        ], $dto->toArray());
    }
}
