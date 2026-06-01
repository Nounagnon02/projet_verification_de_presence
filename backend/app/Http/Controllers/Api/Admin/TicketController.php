<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->per_page, 100);
        $tickets = $query->paginate($perPage ?: 15);

        return $this->paginatedResponse(
            $tickets->through(fn($t) => [
                'id'         => $t->id,
                'user'       => ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email],
                'subject'    => $t->subject,
                'status'     => $t->status,
                'priority'   => $t->priority,
                'category'   => $t->category,
                'messages_count' => $t->messages_count ?? $t->messages()->count(),
                'created_at' => $t->created_at->format('Y-m-d H:i'),
                'updated_at' => $t->updated_at->format('Y-m-d H:i'),
            ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string',
            'priority' => 'sometimes|string|in:basse,moyenne,haute,critique',
            'category' => 'sometimes|string|max:100',
        ]);

        $ticket = SupportTicket::create([
            'user_id'  => $request->user()->id,
            'subject'  => $validated['subject'],
            'message'  => $validated['message'],
            'priority' => $validated['priority'] ?? 'moyenne',
            'category' => $validated['category'] ?? null,
            'status'   => 'ouvert',
        ]);

        return $this->createdResponse([
            'id'         => $ticket->id,
            'subject'    => $ticket->subject,
            'status'     => $ticket->status,
            'priority'   => $ticket->priority,
            'created_at' => $ticket->created_at->format('Y-m-d H:i'),
        ], 'Ticket créé avec succès.');
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load(['user', 'messages.user']);
        return $this->successResponse([
            'id'          => $ticket->id,
            'user'        => ['id' => $ticket->user->id, 'name' => $ticket->user->name, 'email' => $ticket->user->email],
            'subject'     => $ticket->subject,
            'message'     => $ticket->message,
            'status'      => $ticket->status,
            'priority'    => $ticket->priority,
            'category'    => $ticket->category,
            'messages'    => $ticket->messages->map(fn($m) => [
                'id'         => $m->id,
                'user'       => ['id' => $m->user->id, 'name' => $m->user->name],
                'message'    => $m->message,
                'created_at' => $m->created_at->format('Y-m-d H:i'),
            ]),
            'created_at'  => $ticket->created_at->format('Y-m-d H:i'),
            'updated_at'  => $ticket->updated_at->format('Y-m-d H:i'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $msg = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'message'   => $validated['message'],
        ]);

        $ticket->update(['status' => 'en_cours']);

        return $this->createdResponse([
            'id'         => $msg->id,
            'user'       => ['id' => $msg->user_id],
            'message'    => $msg->message,
            'created_at' => $msg->created_at->format('Y-m-d H:i'),
        ], 'Réponse ajoutée.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ouvert,en_cours,resolu,ferme',
        ]);

        $ticket->update(['status' => $validated['status']]);

        return $this->successResponse([
            'id'     => $ticket->id,
            'status' => $ticket->status,
        ], 'Statut mis à jour.');
    }

    public function destroy(SupportTicket $ticket): JsonResponse
    {
        $ticket->messages()->delete();
        $ticket->delete();
        return $this->successResponse(null, 'Ticket supprimé.');
    }
}
