{{--
    Typed footer для expert_pr (Magas): контакты слева + группы ссылок справа в одной строке на lg,
    затем блок «response», затем нижняя полоса — без вертикальной «простыни» из секций подряд.
    @var array $f  резолвер {@see \App\Tenant\Footer\TenantFooterResolver}
    @var list<array<string, mixed>> $sections
--}}
@php
    use App\Tenant\Footer\FooterSectionType;

    $sectionsColl = collect($sections ?? []);
    $contactsBlock = $sectionsColl->firstWhere('type', FooterSectionType::CONTACTS);
    $linkGroupsBlock = $sectionsColl->firstWhere('type', FooterSectionType::LINK_GROUPS);
    $conditionsBlock = $sectionsColl->firstWhere('type', FooterSectionType::CONDITIONS_LIST);
    $bottomBarBlock = $sectionsColl->firstWhere('type', FooterSectionType::BOTTOM_BAR);
@endphp
<div class="border-b border-white/[0.06] bg-gradient-to-b from-[rgb(14_16_20)] via-[rgb(8_10_14)] to-[rgb(4_6_10)]">
    <div class="mx-auto max-w-7xl px-3 py-10 sm:px-4 md:px-8 sm:py-12">
        <div class="grid gap-12 lg:grid-cols-12 lg:gap-x-14 lg:gap-y-10">
            <div class="min-w-0 lg:col-span-5">
                @if($contactsBlock)
                    <div class="expert-pr-footer__contacts">
                        @include('tenant.components.footer-moto.contacts', ['block' => $contactsBlock])
                    </div>
                @endif
            </div>
            <div class="min-w-0 lg:col-span-7">
                @if($linkGroupsBlock)
                    @include('tenant.components.footer-moto.link-groups', ['block' => $linkGroupsBlock])
                @endif
            </div>
        </div>
        @if($conditionsBlock)
            <div class="mt-10 border-t border-white/[0.06] pt-10 lg:mt-12 lg:pt-12">
                @include('tenant.components.footer-moto.conditions-list', ['block' => $conditionsBlock])
            </div>
        @endif
    </div>
</div>
@if($bottomBarBlock)
    <div class="border-t border-white/[0.07] bg-black/40">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-4 md:px-8">
            @include('tenant.components.footer-moto.bottom-bar', ['f' => $f, 'block' => $bottomBarBlock, 'year' => $year, 'siteName' => $siteName])
        </div>
    </div>
@endif
