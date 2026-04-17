<x-filament-panels::page>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium">Клиент</th>
                    <th class="px-3 py-2 font-medium">План</th>
                    <th class="px-3 py-2 font-medium">Override</th>
                    <th class="px-3 py-2 font-medium">Провайдер</th>
                    <th class="px-3 py-2 font-medium">Подписки (CRM)</th>
                    <th class="px-3 py-2 font-medium">Push</th>
                    <th class="px-3 py-2 font-medium">PWA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($this->tenants as $tenant)
                    @php($ps = $tenant->pushSettings)
                    @php($subAgg = \App\TenantPush\TenantPushSettingsView::make($tenant, app(\App\TenantPush\TenantPushFeatureGate::class), app(\App\TenantPush\TenantPushCrmRequestRecipientResolver::class))->subscriptionAggregate->value)
                    <tr>
                        <td class="px-3 py-2">
                            <a href="{{ \App\Filament\Platform\Resources\TenantResource::getUrl('edit', ['record' => $tenant]) }}" class="text-primary-600 hover:underline">
                                {{ $tenant->name }}
                            </a>
                        </td>
                        <td class="px-3 py-2">{{ $tenant->plan?->slug ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $ps?->push_override ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $ps?->provider_status ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $subAgg }}</td>
                        <td class="px-3 py-2">{{ $ps && $ps->is_push_enabled ? 'да' : 'нет' }}</td>
                        <td class="px-3 py-2">{{ $ps && $ps->is_pwa_enabled ? 'да' : 'нет' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
