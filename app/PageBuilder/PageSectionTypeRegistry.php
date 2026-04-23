<?php

namespace App\PageBuilder;

use App\Models\Page;
use App\PageBuilder\Blueprints\BlackDuck\AvailabilityRibbonBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\BeforeAfterSliderBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\CaseStudyCardsBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\MessengerCaptureBarBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\PackageMatrixBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\ServiceHubGridBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\StickyMobileCtaDockBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\VehicleClassSelectorBlueprint;
use App\PageBuilder\Blueprints\BlackDuck\WorksPortfolioBlueprint;
use App\PageBuilder\Blueprints\CardsTeaserBlueprint;
use App\PageBuilder\Blueprints\ContactInquirySectionBlueprint;
use App\PageBuilder\Blueprints\ContactsBlueprint;
use App\PageBuilder\Blueprints\ContactsInfoSectionBlueprint;
use App\PageBuilder\Blueprints\ContentFaqSectionBlueprint;
use App\PageBuilder\Blueprints\CtaBlueprint;
use App\PageBuilder\Blueprints\DataTableSectionBlueprint;
use App\PageBuilder\Blueprints\Expert\CredentialsGridBlueprint;
use App\PageBuilder\Blueprints\Expert\EditorialGalleryBlueprint;
use App\PageBuilder\Blueprints\Expert\EnrollmentCtaStripBlueprint;
use App\PageBuilder\Blueprints\Expert\ExpertHeroBlueprint;
use App\PageBuilder\Blueprints\Expert\ExpertLeadFormBlueprint;
use App\PageBuilder\Blueprints\Expert\FounderExpertBioBlueprint;
use App\PageBuilder\Blueprints\Expert\ImportantConditionsBlueprint;
use App\PageBuilder\Blueprints\Expert\PricingCardsBlueprint;
use App\PageBuilder\Blueprints\Expert\ProblemCardsBlueprint;
use App\PageBuilder\Blueprints\Expert\ProcessStepsBlueprint;
use App\PageBuilder\Blueprints\Expert\ReviewFeedBlueprint;
use App\PageBuilder\Blueprints\Expert\ServiceProgramCardsBlueprint;
use App\PageBuilder\Blueprints\FaqBlueprint;
use App\PageBuilder\Blueprints\FeaturesBlueprint;
use App\PageBuilder\Blueprints\GalleryBlueprint;
use App\PageBuilder\Blueprints\HeroBlueprint;
use App\PageBuilder\Blueprints\InfoCardsSectionBlueprint;
use App\PageBuilder\Blueprints\ListBlockSectionBlueprint;
use App\PageBuilder\Blueprints\MotorcycleCatalogBlueprint;
use App\PageBuilder\Blueprints\NoticeBoxSectionBlueprint;
use App\PageBuilder\Blueprints\RichTextBlueprint;
use App\PageBuilder\Blueprints\StructuredTextSectionBlueprint;
use App\PageBuilder\Blueprints\TextSectionBlueprint;
use App\PageBuilder\Contracts\PageSectionBlueprintInterface;
use InvalidArgumentException;

final class PageSectionTypeRegistry
{
    /** @var array<string, PageSectionBlueprintInterface> */
    private array $byId = [];

    public function __construct()
    {
        foreach ($this->allBlueprintInstances() as $blueprint) {
            $this->byId[$blueprint->id()] = $blueprint;
        }
    }

    /**
     * Landing / главная страница — каталог в builder.
     *
     * @return list<PageSectionBlueprintInterface>
     */
    public function landingBlueprintInstances(): array
    {
        return [
            new HeroBlueprint,
            new RichTextBlueprint,
            new FeaturesBlueprint,
            new CtaBlueprint,
            new FaqBlueprint,
            new ContactsBlueprint,
            new GalleryBlueprint,
            new CardsTeaserBlueprint,
            new MotorcycleCatalogBlueprint,
            new ExpertHeroBlueprint,
            new ProblemCardsBlueprint,
            new CredentialsGridBlueprint,
            new ServiceProgramCardsBlueprint,
            new ProcessStepsBlueprint,
            new ImportantConditionsBlueprint,
            new PricingCardsBlueprint,
            new ReviewFeedBlueprint,
            new EditorialGalleryBlueprint,
            new FounderExpertBioBlueprint,
            new EnrollmentCtaStripBlueprint,
            new ExpertLeadFormBlueprint,
            new ServiceHubGridBlueprint,
            new BeforeAfterSliderBlueprint,
            new StickyMobileCtaDockBlueprint,
            new AvailabilityRibbonBlueprint,
            new VehicleClassSelectorBlueprint,
            new CaseStudyCardsBlueprint,
            new PackageMatrixBlueprint,
            new MessengerCaptureBarBlueprint,
        ];
    }

    /**
     * Обычные контентные страницы (slug != home) — каталог в builder.
     *
     * @return list<PageSectionBlueprintInterface>
     */
    public function contentPageBlueprintInstances(): array
    {
        return [
            new HeroBlueprint,
            new StructuredTextSectionBlueprint,
            new TextSectionBlueprint,
            new ContentFaqSectionBlueprint,
            new ListBlockSectionBlueprint,
            new InfoCardsSectionBlueprint,
            new ContactsInfoSectionBlueprint,
            new ContactInquirySectionBlueprint,
            new DataTableSectionBlueprint,
            new NoticeBoxSectionBlueprint,
            new ProblemCardsBlueprint,
            new ExpertLeadFormBlueprint,
            new EnrollmentCtaStripBlueprint,
            new CtaBlueprint,
            new ServiceHubGridBlueprint,
            new BeforeAfterSliderBlueprint,
            new StickyMobileCtaDockBlueprint,
            new AvailabilityRibbonBlueprint,
            new VehicleClassSelectorBlueprint,
            new CaseStudyCardsBlueprint,
            new WorksPortfolioBlueprint,
            new PackageMatrixBlueprint,
            new MessengerCaptureBarBlueprint,
        ];
    }

    /**
     * @return list<PageSectionBlueprintInterface>
     */
    private function allBlueprintInstances(): array
    {
        return array_merge($this->landingBlueprintInstances(), $this->contentPageBlueprintInstances());
    }

    public function get(string $id): PageSectionBlueprintInterface
    {
        return $this->byId[$id] ?? throw new InvalidArgumentException("Unknown page section type: {$id}");
    }

    public function has(string $id): bool
    {
        return isset($this->byId[$id]);
    }

    /**
     * @return list<PageSectionBlueprintInterface>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /**
     * Types available for the given theme (catalog + create).
     *
     * @return list<PageSectionBlueprintInterface>
     */
    public function forTheme(string $themeKey): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (PageSectionBlueprintInterface $b): bool => $b->supportsTheme($themeKey)
        ));
    }

    /**
     * Каталог для вкладки builder с учётом страницы: home — landing, иначе — контентные блоки.
     *
     * @return list<PageSectionBlueprintInterface>
     */
    public function forPage(Page $page, string $themeKey): array
    {
        $source = $page->slug === 'home'
            ? $this->landingBlueprintInstances()
            : $this->contentPageBlueprintInstances();

        return array_values(array_filter(
            $source,
            fn (PageSectionBlueprintInterface $b): bool => $b->supportsTheme($themeKey)
        ));
    }

    /**
     * @return array<string, list<PageSectionBlueprintInterface>>
     */
    public function groupedForPage(Page $page, string $themeKey): array
    {
        $categories = $page->slug === 'home'
            ? PageSectionCategory::orderedForCatalog()
            : PageSectionCategory::orderedForContentPageCatalog();

        $out = [];
        foreach ($categories as $cat) {
            $out[$cat->value] = [];
        }
        foreach ($this->forPage($page, $themeKey) as $b) {
            $out[$b->category()->value][] = $b;
        }

        return $out;
    }

    public function typeAllowedOnPage(string $typeId, Page $page, string $themeKey): bool
    {
        if (! $this->has($typeId)) {
            return false;
        }
        foreach ($this->forPage($page, $themeKey) as $b) {
            if ($b->id() === $typeId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<PageSectionBlueprintInterface>> category value => blueprints
     */
    public function groupedForTheme(string $themeKey): array
    {
        $out = [];
        foreach (PageSectionCategory::orderedForCatalog() as $cat) {
            $out[$cat->value] = [];
        }
        foreach ($this->forTheme($themeKey) as $b) {
            $out[$b->category()->value][] = $b;
        }

        return $out;
    }
}
