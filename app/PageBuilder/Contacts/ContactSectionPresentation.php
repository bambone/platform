<?php

namespace App\PageBuilder\Contacts;

/**
 * @phpstan-type PrimaryList list<ResolvedContactChannel>
 * @phpstan-type SecondaryList list<ResolvedContactChannel>
 */
final readonly class ContactSectionPresentation
{
    /**
     * @param  list<ResolvedContactChannel>  $primaryChannels
     * @param  list<ResolvedContactChannel>  $secondaryChannels
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $address,
        public string $workingHours,
        public ContactMapResolvedView $mapBlock,
        public string $additionalNote,
        public array $primaryChannels,
        public array $secondaryChannels,
    ) {}

    public function hasSectionHeading(): bool
    {
        return $this->title !== '';
    }

    public function hasDescription(): bool
    {
        return $this->description !== '';
    }

    public function hasAddress(): bool
    {
        return $this->address !== '';
    }

    public function hasWorkingHours(): bool
    {
        return $this->workingHours !== '';
    }

    public function hasMap(): bool
    {
        return $this->mapBlock->shouldRenderMapBlock();
    }

    public function hasAdditionalNote(): bool
    {
        return $this->additionalNote !== '';
    }

    public function hasAnyUsableChannel(): bool
    {
        return $this->primaryChannels !== [] || $this->secondaryChannels !== [];
    }

    /**
     * @return list<ResolvedContactChannel>
     */
    public function allUsableChannels(): array
    {
        return array_merge($this->primaryChannels, $this->secondaryChannels);
    }

    public function shouldRenderShell(): bool
    {
        return $this->hasSectionHeading()
            || $this->hasDescription()
            || $this->hasAnyUsableChannel()
            || $this->hasAddress()
            || $this->hasWorkingHours()
            || $this->hasMap()
            || $this->hasAdditionalNote();
    }
}
