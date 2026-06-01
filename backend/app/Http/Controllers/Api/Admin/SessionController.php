<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn($s) => [
                'id'            => $s->id,
                'ip_address'    => $s->ip_address,
                'user_agent'    => $s->user_agent,
                'is_current'    => $s->id === session()->getId(),
                'last_active'   => $s->last_activity
                    ? now()->createFromTimestamp($s->last_activity)->diffForHumans()
                    : null,
            ]);

        return $this->successResponse($sessions);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $currentSessionId = session()->getId();

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return $this->successResponse(null, 'Autres sessions déconnectées.');
    }
}
