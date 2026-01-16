<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Models\Redemption;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RewardController extends Controller
{
    public function index()
    {
        $rewards = Reward::where('is_active', true)->get();
        // Assuming the authenticated user is linked to a Member, or we are viewing as a Member?
        // In this project context, it seems Dashboard is for Admin/User who manages Members.
        // But the "Shop" might be for Members?
        // Based on "PresenceController", there is dashboard/dashboardV.
        
        // Let's assume this view is accessible by the logged in User who manages Members, OR by Members themselves if they have login.
        // If it's for the User/Admin to see what rewards exist:
        
        return view('rewards.index', compact('rewards'));
    }

    // Purchase method - this might need more context on who is logged in.
    // If it's a kiosque or admin redeeming for a student:
    public function redeem(Request $request, $rewardId)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id'
        ]);

        $member = Member::findOrFail($request->member_id);
        $reward = Reward::findOrFail($rewardId);

        if ($member->points < $reward->cost) {
            return back()->with('error', 'Points insuffisants !');
        }

        if ($reward->stock == 0) {
            return back()->with('error', 'Stock épuisé !');
        }

        // Transaction
        $member->decrement('points', $reward->cost);
        if ($reward->stock > 0) {
            $reward->decrement('stock');
        }

        Redemption::create([
            'member_id' => $member->id,
            'reward_id' => $reward->id,
            'points_spent' => $reward->cost,
        ]);

        return back()->with('success', 'Récompense récupérée avec succès !');
    }
}
