<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketsController extends Controller
{
    public function getTickets(Request $request)
    {
        $tickets = Ticket::orderByDesc('id')->paginate(50);

        return response()->json(['success' => true, 'tickets' => $tickets]);
    }

    public function buyTicket(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'ticket_id' => 'required|exists:App\Models\Ticket,id',
            'qty' => 'required|integer|min:1|max:20',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => $validate->errors()]);
        }

        $qty = (int) $request->qty;
        $userId = (int) auth()->id();

        try {
            // Balance + stock check, deduction, tickets and ledger row in one transaction with row locks.
            $result = DB::transaction(function () use ($request, $qty, $userId) {
                $ticket = Ticket::query()->whereKey($request->ticket_id)->lockForUpdate()->firstOrFail();
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $total = (int) $ticket->price * $qty;

                if ($user->realPoints() < $total) {
                    return 'Not enough points';
                }

                if ((int) $ticket->count > 0) {
                    $sold = UserTicket::query()->where('ticket_id', $ticket->id)->count();
                    if ($sold + $qty > (int) $ticket->count) {
                        return 'Ticket limit reached';
                    }
                }

                $user->points = (int) $user->points - $total;
                $user->save();

                for ($i = 0; $i < $qty; $i++) {
                    UserTicket::query()->create(['user_id' => $user->id, 'ticket_id' => $ticket->id]);
                }

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => $total,
                    'type' => 'outcome',
                    'system' => 'Points',
                    'status' => true,
                    'comment' => $qty.' tickets',
                ]);

                return (int) $user->points;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Some error']);
        }

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tickets purchased successfully',
            'points' => $result,
        ]);
    }

    public function myTickets(Request $request)
    {
        $user = auth()->user();
        $tickets = $user->purchased_tickets;

        return response()->json(['success' => true, 'tickets' => $tickets]);
    }
}
