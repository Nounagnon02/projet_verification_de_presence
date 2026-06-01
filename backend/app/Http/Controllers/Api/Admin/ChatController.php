<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $query = ChatConversation::with(['user', 'lastMessage'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($search = $request->search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate(20);

        return $this->paginatedResponse(
            $conversations->through(fn($c) => [
                'id'            => $c->id,
                'user'          => ['id' => $c->user->id, 'name' => $c->user->name],
                'title'         => $c->title,
                'status'        => $c->status,
                'department'    => $c->department,
                'last_message'  => $c->lastMessage ? [
                    'message'    => str($c->lastMessage->message)->limit(80),
                    'created_at' => $c->lastMessage->created_at->format('Y-m-d H:i'),
                    'is_admin'   => $c->lastMessage->is_admin,
                ] : null,
                'unread'        => $c->messages()->where('is_admin', false)->whereNull('read_at')->count(),
                'created_at'    => $c->created_at->format('Y-m-d H:i'),
            ])
        );
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $conversation->load('user');

        $messages = $conversation->messages()
            ->with('user')
            ->oldest()
            ->paginate(50);

        return $this->paginatedResponse(
            $messages->through(fn($m) => [
                'id'         => $m->id,
                'user'       => ['id' => $m->user->id, 'name' => $m->user->name],
                'message'    => $m->message,
                'is_admin'   => $m->is_admin,
                'created_at' => $m->created_at->format('Y-m-d H:i'),
            ])
        );
    }

    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $msg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'message'         => $validated['message'],
            'is_admin'        => true,
        ]);

        $conversation->update(['status' => 'actif']);

        $msg->load('user');

        return $this->createdResponse([
            'id'         => $msg->id,
            'user'       => ['id' => $msg->user->id, 'name' => $msg->user->name],
            'message'    => $msg->message,
            'is_admin'   => $msg->is_admin,
            'created_at' => $msg->created_at->format('Y-m-d H:i'),
        ], 'Message envoyé.');
    }

    public function closeConversation(ChatConversation $conversation): JsonResponse
    {
        $conversation->update(['status' => 'ferme']);
        return $this->successResponse(null, 'Conversation fermée.');
    }
}
