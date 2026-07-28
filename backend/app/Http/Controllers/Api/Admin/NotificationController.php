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
        // Chaque utilisateur ne voit QUE ses propres notifications (colonne
        // user_id). Sans ce filtre, tout admin voyait celles de tout le monde.
        $query = Notification::where('user_id', $request->user()->id)->latest();

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

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse([
            'count' => Notification::where('user_id', $request->user()->id)->unread()->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureOwner($request, $notification);

        $notification->markAsRead();
        return $this->successResponse(null, 'Notification marquée comme lue.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        // Ne marque que les notifications de l'utilisateur : auparavant un seul
        // « tout marquer lu » vidait le non-lu de TOUS les utilisateurs.
        $count = Notification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);
        return $this->successResponse(['marked_read' => $count], 'Toutes les notifications sont marquées comme lues.');
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureOwner($request, $notification);

        $notification->delete();
        return $this->successResponse(null, 'Notification supprimée.');
    }

    /**
     * Coupe court (404) si la notification n'appartient pas à l'utilisateur.
     */
    private function ensureOwner(Request $request, Notification $notification): void
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(404, 'Notification non trouvée.');
        }
    }
}
