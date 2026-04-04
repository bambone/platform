<?php

namespace App\View\Components\Tenant;

use App\Support\PageRichContent;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Единые классы typography + таблицы + медиа для HTML из RichEditor (витрина тенанта).
 */
final class RichProse extends Component
{
    public function __construct(
        public mixed $content = null,
        public string $variant = 'policy',
    ) {}

    public function html(): string
    {
        return PageRichContent::toHtml($this->content);
    }

    public function proseClass(): string
    {
        $tableMedia = <<<'EOT'
            [&_table]:w-full [&_table]:min-w-[min(100%,32rem)] [&_table]:border-collapse [&_table]:border [&_table]:border-white/10 [&_table]:text-left [&_table]:text-[0.9375rem] sm:[&_table]:text-sm
            [&_thead]:border-b [&_thead]:border-white/15
            [&_th]:border [&_th]:border-white/10 [&_th]:bg-white/[0.05] [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:font-medium [&_th]:text-white
            [&_td]:border-t [&_td]:border-white/10 [&_td]:px-4 [&_td]:py-3 [&_td]:text-silver/90
            [&_tbody_tr:nth-child(even)]:bg-white/[0.03] [&_table>tr:nth-child(even)]:bg-white/[0.025]
            [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_figure]:my-6 [&_figure_img]:mx-auto
            [&_picture_img]:max-w-full [&_picture_img]:h-auto [&_picture]:block [&_picture]:my-6
            prose-hr:border-white/10 prose-hr:my-8
            prose-pre:max-w-full prose-pre:overflow-x-auto prose-pre:border prose-pre:border-white/10 prose-pre:bg-white/[0.06] prose-pre:text-[0.8125rem] prose-pre:leading-relaxed prose-pre:text-silver/90 sm:prose-pre:text-sm
            prose-code:rounded-md prose-code:bg-white/[0.06] prose-code:px-1 prose-code:py-0.5 prose-code:text-amber-100/90 prose-code:before:content-none prose-code:after:content-none
            [&_p[style*='text-align']]:max-w-none
        EOT;
        $tableMedia = preg_replace('/\s+/', ' ', trim($tableMedia)) ?? '';

        return match ($this->variant) {
            'policy' => 'prose prose-invert prose-sm sm:prose-base max-w-none overflow-x-auto '
                .'prose-p:leading-[1.75] prose-p:text-[0.9375rem] sm:prose-p:text-base prose-p:text-silver/90 '
                .'prose-ul:text-silver/90 prose-ol:text-silver/90 '
                .'prose-li:marker:text-moto-amber prose-li:my-1 '
                .'prose-strong:text-white '
                .'prose-a:text-moto-amber prose-a:font-medium prose-a:no-underline hover:prose-a:underline '
                .'prose-headings:text-white prose-headings:font-bold '
                .'prose-blockquote:border-l-moto-amber/80 prose-blockquote:border-white/10 prose-blockquote:bg-white/[0.04] prose-blockquote:py-2 prose-blockquote:not-italic prose-blockquote:text-silver/85 '
                .$tableMedia,
            'callout' => 'prose prose-invert prose-sm max-w-none overflow-x-auto text-silver/90 '
                .'prose-p:leading-relaxed prose-p:text-silver/90 prose-li:marker:text-moto-amber prose-strong:text-white sm:prose-base '
                .$tableMedia,
            'intro' => 'prose prose-invert prose-sm max-w-3xl overflow-x-auto text-silver/95 '
                .'prose-p:font-medium prose-p:leading-[1.7] prose-p:text-silver/90 prose-a:text-moto-amber prose-a:font-semibold prose-strong:text-white sm:prose-base '
                .$tableMedia,
            'simple' => 'prose prose-invert max-w-3xl overflow-x-auto text-sm text-silver/95 '
                .'prose-p:leading-relaxed prose-a:text-moto-amber prose-strong:text-white sm:text-base '
                .$tableMedia,
            'default' => 'prose prose-invert prose-sm max-w-none overflow-x-auto text-silver '
                .'prose-headings:text-white prose-p:leading-relaxed sm:prose-base '
                .$tableMedia,
            'notice' => 'prose prose-invert prose-sm max-w-none overflow-x-auto sm:prose-base '
                .'prose-p:my-2 prose-p:leading-relaxed prose-a:text-inherit prose-strong:text-white '
                .$tableMedia,
            default => 'prose prose-invert prose-sm sm:prose-base max-w-none overflow-x-auto text-silver/90 '.$tableMedia,
        };
    }

    public function render(): View
    {
        return view('components.tenant.rich-prose', [
            'htmlContent' => $this->html(),
            'proseClasses' => $this->proseClass(),
        ]);
    }
}
