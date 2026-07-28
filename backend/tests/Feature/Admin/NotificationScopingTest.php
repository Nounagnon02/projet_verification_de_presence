<?php

namespace Tests\Feature\Admin;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les notifications sont propres à chaque utilisateur (colonne user_id) :
 * un utilisateur ne voit, ne marque ni ne supprime que les siennes.
 */
class NotificationScopingTest extends TestCase
{
    public function test_un_utilisateur_ne_voit_que_ses_notifications(): void
    {
        $a = User::factory()->create(['email' => 'na-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        $b = User::factory()->create(['email' => 'nb-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        $notifA = Notification::create(['user_id' => $a->id, 'type' => 'info', 'title' => 'A', 'message' => 'à A']);
        $notifB = Notification::create(['user_id' => $b->id, 'type' => 'info', 'title' => 'B', 'message' => 'à B']);

        $r = $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->getJson('/api/admin/notifications');
        $r->assertStatus(200);
        $ids = collect($r->json('data'))->pluck('id')->all();
        $this->assertContains($notifA->id, $ids);
        $this->assertNotContains($notifB->id, $ids);
    }

    public function test_mark_all_read_naffecte_que_ses_notifications(): void
    {
        $a = User::factory()->create(['email' => 'ma-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        $b = User::factory()->create(['email' => 'mb-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        Notification::create(['user_id' => $a->id, 'type' => 'info', 'title' => 'A', 'message' => 'm']);
        $notifB = Notification::create(['user_id' => $b->id, 'type' => 'info', 'title' => 'B', 'message' => 'm']);

        $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->postJson('/api/admin/notifications/read-all')->assertStatus(200);

        // La notification de B reste non lue.
        $this->assertNull($notifB->fresh()->read_at);
    }

    public function test_impossible_de_marquer_lue_la_notification_dun_autre(): void
    {
        $a = User::factory()->create(['email' => 'ra-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        $b = User::factory()->create(['email' => 'rb-' . Str::random(5) . '@x.test', 'must_change_password' => false, 'role' => 'super_admin']);
        $notifB = Notification::create(['user_id' => $b->id, 'type' => 'info', 'title' => 'B', 'message' => 'm']);

        $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->postJson('/api/admin/notifications/' . $notifB->id . '/read')->assertStatus(404);
        $this->assertNull($notifB->fresh()->read_at);
    }
}
