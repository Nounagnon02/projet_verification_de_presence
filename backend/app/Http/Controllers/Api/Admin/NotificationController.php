<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::latest();

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $perPage = min((int) $request->per_page, 50);
        $notifications = $query->paginate($perPage ?: 20);

        return $this->paginatedResponse(
            $notifications->through(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'link'       => $n->link,
                'is_read'    => $n->isRead(),
                'created_at' => $n->created_at->format('Y-m-d H:i'),
            ])
        );
    }

    public function unreadCount(): JsonResponse
    {
        return $this->successResponse([
            'count' => Notification::unread()->count(),
        ]);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $notification->markAsRead();
        return $this->successResponse(null, 'Notification marquée comme lue.');
    }

    public function markAllRead(): JsonResponse
    {
        $count = Notification::unread()->update(['read_at' => now()]);
        return $this->successResponse(['marked_read' => $count], 'Toutes les notifications sont marquées comme lues.');
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();
        return $this->successResponse(null, 'Notification supprimée.');
    }
}
