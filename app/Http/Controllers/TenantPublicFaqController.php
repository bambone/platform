<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Contracts\View\View;

class TenantPublicFaqController extends Controller
{
    public function __invoke(): View
    {
        $t = tenant();
        abort_if($t === null, 404);

        $faqs = Faq::query()
            ->where('status', 'published')
            ->forPublicHubAndFaqPage()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $isBlackDuck = ((string) $t->theme_key) === 'black_duck';
        $faqPageIntroLine1 = $isBlackDuck
            ? 'Кратко о записи, сроках и порядке работ. Точный план и смета по вашему авто — после осмотра или согласованной заявки.'
            : 'Краткие ответы на частые вопросы по срокам, гарантии и записи.';

        return tenant_view('pages.faq', [
            'faqs' => $faqs,
            'faqPageIntroLine1' => $faqPageIntroLine1,
        ]);
    }
}
