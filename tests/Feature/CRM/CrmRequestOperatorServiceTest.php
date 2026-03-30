<?php

namespace Tests\Feature\CRM;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Models\CrmRequestNote;
use App\Models\User;
use App\Product\CRM\CrmRequestOperatorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class CrmRequestOperatorServiceTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_change_status_sets_processed_at_only_once_when_leaving_new(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $crm = $this->makeCrmRequest(null, ['status' => CrmRequest::STATUS_NEW]);
        $this->assertNull($crm->processed_at);

        $svc = app(CrmRequestOperatorService::class);
        $svc->changeStatus($user, $crm, CrmRequest::STATUS_IN_REVIEW);
        $crm->refresh();
        $this->assertNotNull($crm->processed_at);
        $firstProcessed = $crm->processed_at->copy();

        $svc->changeStatus($user, $crm, CrmRequest::STATUS_QUALIFIED);
        $crm->refresh();
        $this->assertTrue($firstProcessed->equalTo($crm->processed_at));

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $crm->id,
            'type' => CrmRequestActivity::TYPE_STATUS_CHANGED,
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_add_note_creates_row_activity_and_timestamps(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $crm = $this->makeCrmRequest(null);
        $this->assertNull($crm->last_commented_at);

        $svc = app(CrmRequestOperatorService::class);
        $svc->addNote($user, $crm, '  Операторский комментарий  ');

        $this->assertSame(1, CrmRequestNote::query()->where('crm_request_id', $crm->id)->count());
        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $crm->id,
            'type' => CrmRequestActivity::TYPE_NOTE_ADDED,
            'actor_user_id' => $user->id,
        ]);

        $crm->refresh();
        $this->assertNotNull($crm->last_commented_at);
        $this->assertNotNull($crm->last_activity_at);
    }

    public function test_update_summary_activity_meta_preview_and_cleared(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $crm = $this->makeCrmRequest(null, ['internal_summary' => null]);
        $svc = app(CrmRequestOperatorService::class);

        $svc->updateSummary($user, $crm, 'Короткое резюме для оператора');
        $crm->refresh();
        $row = CrmRequestActivity::query()
            ->where('crm_request_id', $crm->id)
            ->where('type', CrmRequestActivity::TYPE_SUMMARY_UPDATED)
            ->latest('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('Короткое резюме для оператора', $row->meta['preview'] ?? null);
        $this->assertTrue((bool) ($row->meta['first'] ?? false));

        $long = str_repeat('а', 150);
        $svc->updateSummary($user, $crm, $long);
        $row = CrmRequestActivity::query()
            ->where('crm_request_id', $crm->id)
            ->where('type', CrmRequestActivity::TYPE_SUMMARY_UPDATED)
            ->latest('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertStringEndsWith('…', (string) ($row->meta['preview'] ?? ''));
        $this->assertSame(118, mb_strlen((string) $row->meta['preview']));
        $this->assertArrayNotHasKey('first', $row->meta ?? []);

        $svc->updateSummary($user, $crm, null);
        $row = CrmRequestActivity::query()
            ->where('crm_request_id', $crm->id)
            ->where('type', CrmRequestActivity::TYPE_SUMMARY_UPDATED)
            ->latest('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) ($row->meta['cleared'] ?? false));
    }
}
