<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackage;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientPointsController extends Controller
{
    /** POST /dashboard/clients/{client}/points {delta, comment}: a compensation or a correction, never below zero. */
    public function adjust(Request $request, User $client)
    {
        abort_unless((int) $client->role === User::ROLE_USER, 404);
        $data = $request->validate([
            'delta' => 'required|integer|between:-100000,100000|not_in:0',
            'comment' => 'required|string|max:120',
        ]);
        $delta = (int) $data['delta'];

        $done = DB::transaction(function () use ($client, $delta, $data, $request) {
            $user = User::query()->lockForUpdate()->findOrFail($client->id);
            if ((int) $user->points + $delta < 0) {
                return false;
            }
            $before = (int) $user->points;
            $user->points = $before + $delta;
            $user->save();
            Transaction::create([
                'user_id' => $user->id,
                'amount' => abs($delta),
                'type' => $delta > 0 ? 'income' : 'outcome',
                'system' => 'Admin',
                'status' => true,
                'comment' => $data['comment'],
            ]);
            AdminAudit::record('user', (int) $user->id, 'points', 'points', (string) $before, (string) $user->points, (int) $request->user()->id);

            return true;
        });

        return redirect()->route('admin.clients.show', $client)
            ->with($done ? 'message' : 'error', __($done ? 'admin.points_adjusted' : 'admin.points_adjust_negative'));
    }

    /** The admin's switch for a package's auto-renewal (only where a card is kept). */
    public function renewal(User $client, UserPackage $pack)
    {
        abort_unless((int) $pack->user_id === (int) $client->id && filled($pack->rectoken), 404);
        $pack->forceFill(['auto_renew' => ! $pack->renews()])->save();

        return redirect()->route('admin.clients.packages', $client->id)->with('message', __('admin.auto_renew_saved'));
    }
}
