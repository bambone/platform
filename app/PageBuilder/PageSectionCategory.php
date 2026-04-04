<?php

namespace App\PageBuilder;

enum PageSectionCategory: string
{
    case Basic = 'basic';
    case Conversion = 'conversion';
    case Content = 'content';
    case SocialProof = 'social_proof';
    case Contacts = 'contacts';
    case Catalog = 'catalog';
    case PageContent = 'page_content';
    case StructureLists = 'structure_lists';
    case HelpNotices = 'help_notices';
    case InfoBlocks = 'info_blocks';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Базовые',
            self::Conversion => 'Продающие',
            self::Content => 'Контентные',
            self::SocialProof => 'Социальное доверие',
            self::Contacts => 'Контакты',
            self::Catalog => 'Каталог и карточки',
            self::PageContent => 'Контент страницы',
            self::StructureLists => 'Структура и списки',
            self::HelpNotices => 'Вопросы и важное',
            self::InfoBlocks => 'Инфоблоки',
        };
    }

    /**
     * @return list<self>
     */
    public static function orderedForCatalog(): array
    {
        return [
            self::Basic,
            self::Content,
            self::Conversion,
            self::SocialProof,
            self::Contacts,
            self::Catalog,
        ];
    }

    /**
     * @return list<self>
     */
    public static function orderedForContentPageCatalog(): array
    {
        return [
            self::Basic,
            self::PageContent,
            self::StructureLists,
            self::HelpNotices,
            self::Contacts,
            self::InfoBlocks,
        ];
    }
}
