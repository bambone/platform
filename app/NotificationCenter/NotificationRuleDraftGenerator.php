<?php

namespace App\NotificationCenter;

use App\Models\CrmRequest;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent draft rules for {@see NotificationSubscription} from CRM inbound history.
 */
final class NotificationRuleDraftGenerator
{
    /**
     * @return array{created: int, skipped: int}
     */
    public function generateForCurrentUser(): array
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || ! $user instanceof User) {
            return ['created' => 0, 'skipped' => 0];
        }

        return DB::transaction(function () use ($tenant, $user): array {
            $userInApp = $this->ensureUserInAppDestination($tenant, $user);
            $webPush = NotificationDestination::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', NotificationChannelType::WebPush->value)
                ->where('user_id', $user->id)
                ->first();

            $destinationIds = array_values(array_filter([$userInApp?->id, $webPush?->id]));
            if ($destinationIds === []) {
                return ['created' => 0, 'skipped' => 0];
            }

            $pairs = CrmRequest::query()
                ->where('tenant_id', $tenant->id)
                ->select(['source', 'request_type'])
                ->distinct()
                ->get();

            $created = 0;
            $skipped = 0;

            if ($pairs->isEmpty()) {
                $result = $this->createRuleIfMissing(
                    $tenant,
                    $user,
                    null,
                    'Новая заявка (черновик, общее правило)',
                    $destinationIds,
                );
                if ($result === 'created') {
                    $created++;
                } else {
                    $skipped++;
                }

                return ['created' => $created, 'skipped' => $skipped];
            }

            foreach ($pairs as $row) {
                $meta = [];
                if ($row->source !== null && $row->source !== '') {
                    $meta['source'] = $row->source;
                }
                if ($row->request_type !== null && $row->request_type !== '') {
                    $meta['request_type'] = $row->request_type;
                }
                $conditions = $meta === [] ? null : ['meta' => $meta];
                $labelParts = [];
                if ($row->source !== null && $row->source !== '') {
                    $labelParts[] = 'источник: '.$row->source;
                }
                if ($row->request_type !== null && $row->request_type !== '') {
                    $labelParts[] = 'тип: '.$row->request_type;
                }
                $name = 'Новая заявка — '.(implode(', ', $labelParts) ?: 'черновик');

                $result = $this->createRuleIfMissing(
                    $tenant,
                    $user,
                    $conditions,
                    $name,
                    $destinationIds,
                );
                if ($result === 'created') {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * Личный in-app получатель для текущего пользователя (согласовано с UI «Получателей» без manage_notifications).
     */
    private function ensureUserInAppDestination(Tenant $tenant, User $user): ?NotificationDestination
    {
        $existing = NotificationDestination::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', NotificationChannelType::InApp->value)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'name' => 'В кабинете (личный)',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => false,
            'config_json' => [],
        ]);
    }

    /**
     * @param  list<int>  $destinationIds
     */
    private function createRuleIfMissing(
        Tenant $tenant,
        User $user,
        ?array $conditions,
        string $name,
        array $destinationIds,
    ): string {
        if ($this->subscriptionExists($tenant, $user, 'crm_request.created', $conditions)) {
            return 'skipped';
        }

        $subscription = NotificationSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'name' => $name,
            'event_key' => 'crm_request.created',
            'enabled' => false,
            'conditions_json' => $conditions,
            'schedule_json' => null,
            'severity_min' => null,
            'created_by_user_id' => $user->id,
        ]);

        $sync = [];
        $order = 0;
        foreach ($destinationIds as $id) {
            $sync[(int) $id] = [
                'delivery_mode' => 'immediate',
                'delay_seconds' => null,
                'order_index' => $order++,
                'is_enabled' => true,
            ];
        }
        $subscription->destinations()->sync($sync);

        return 'created';
    }

    private function subscriptionExists(Tenant $tenant, User $user, string $eventKey, ?array $conditions): bool
    {
        return NotificationSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->get()
            ->contains(fn (NotificationSubscription $s): bool => $this->conditionsEqual($s->conditions_json, $conditions));
    }

    private function conditionsEqual(?array $a, ?array $b): bool
    {
        $ca = $this->canonicalizeForJsonCompare($a ?? []);
        $cb = $this->canonicalizeForJsonCompare($b ?? []);

        return json_encode($ca, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            === json_encode($cb, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>|list<mixed>
     */
    private function canonicalizeForJsonCompare(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $v): mixed => is_array($v) ? $this->canonicalizeForJsonCompare($v) : $v,
                $value,
            );
        }

        ksort($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = is_array($v) ? $this->canonicalizeForJsonCompare($v) : $v;
        }

        return $out;
    }
}
