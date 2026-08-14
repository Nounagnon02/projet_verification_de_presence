<?php

namespace Tests\Feature\Admin;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les tickets de support sont privés à leur créateur : un administrateur ne
 * voit, ne consulte ni ne modifie que les siens.
 */
class TicketScopingTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'tk-' . Str::random(6) . '@x.test',
            'role'  => 'super_admin',
            'must_change_password' => false,
        ]);
    }

    public function test_un_admin_ne_voit_que_ses_tickets(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        SupportTicket::create(['user_id' => $a->id, 'subject' => 'A', 'message' => 'm', 'status' => 'ouvert', 'priority' => 'moyenne']);
        $ticketB = SupportTicket::create(['user_id' => $b->id, 'subject' => 'B', 'message' => 'm', 'status' => 'ouvert', 'priority' => 'moyenne']);

        $r = $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->getJson('/api/admin/tickets');
        $r->assertStatus(200);
        $ids = collect($r->json('data'))->pluck('id')->all();
        $this->assertNotContains($ticketB->id, $ids);
    }

    public function test_consulter_le_ticket_dun_autre_est_refuse(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $ticketB = SupportTicket::create(['user_id' => $b->id, 'subject' => 'B', 'message' => 'm', 'status' => 'ouvert', 'priority' => 'moyenne']);

        $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->getJson('/api/admin/tickets/' . $ticketB->id)
            ->assertStatus(404);
    }

    public function test_supprimer_le_ticket_dun_autre_est_refuse(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $ticketB = SupportTicket::create(['user_id' => $b->id, 'subject' => 'B', 'message' => 'm', 'status' => 'ouvert', 'priority' => 'moyenne']);

        $this->withHeader('Authorization', 'Bearer ' . $a->createToken('t')->plainTextToken)
            ->deleteJson('/api/admin/tickets/' . $ticketB->id)
            ->assertStatus(404);
        $this->assertNotNull($ticketB->fresh());
    }
}
