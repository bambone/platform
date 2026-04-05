<?php

namespace App\NotificationCenter;

use InvalidArgumentException;

/**
 * Immutable semantic payload stored in notification_events.payload_json.
 */
final readonly class NotificationPayloadDto
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl,
        public ?string $actionLabel,
        public array $meta = [],
    ) {}

    /**
     * @return array{title: string, body: string, action_url: ?string, action_label: ?string, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromStoredArray(array $data): self
    {
        [$actionUrl, $actionLabel] = self::normalizedActionFields(
            isset($data['action_url']) ? (string) $data['action_url'] : null,
            isset($data['action_label']) ? (string) $data['action_label'] : null,
        );

        return new self(
            title: (string) ($data['title'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidatedArray(array $data): self
    {
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Notification payload requires non-empty title and body.');
        }

        [$actionUrl, $actionLabel] = self::normalizedActionFields(
            isset($data['action_url']) ? (string) $data['action_url'] : null,
            isset($data['action_label']) ? (string) $data['action_label'] : null,
        );

        return new self(
            title: $title,
            body: $body,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function normalizedActionFields(?string $urlRaw, ?string $labelRaw): array
    {
        $url = $urlRaw === null ? '' : trim($urlRaw);
        if ($url === '') {
            return [null, null];
        }

        $label = $labelRaw === null ? '' : trim($labelRaw);

        return [$url, $label === '' ? null : $label];
    }

    public function assertValidForRecording(): void
    {
        if (trim($this->title) === '' || trim($this->body) === '') {
            throw new InvalidArgumentException('Notification payload requires non-empty title and body.');
        }
    }
}
