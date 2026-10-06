<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Transaction;
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
            'qty' => 'required|numeric|min:1',
        ]);

        $user = auth()->user();

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => $validate->errors()]);
        }

        $ticket = Ticket::find($request->ticket_id);

        $points = $user->points ?? 0;
        $ticketPrice = $ticket->price;

        if($points < $ticketPrice * $request->qty) {
            return response()->json(['success' => false, 'message' => 'Not enough points']);
        }

        if($ticket->count > 0)
        {
            $userTicketsCount = UserTicket::where('ticket_id', $ticket->id)->count();

            if($userTicketsCount >= $ticket->count) {
                return response()->json(['success' => false, 'message' => 'Ticket limit reached']);
            }
        }

        for($i = 0; $i < $request->qty; $i++)
        {
            UserTicket::create([
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
            ]);
        }

        DB::transaction(function () use ($user, $ticket, $request) {
            $user->points -= $ticket->price * $request->qty;
            $user->update();

            Transaction::create([
                'user_id' => $user->id,
                'amount' => $ticket->price * $request->qty,
                'type' => 'outcome',
                'system' => 'Points',
                'status' => true,
                'comment' => $request->qty . ' tickets'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Tickets purchased successfully',
            'points' => $user->points
        ]);
    }

    public function myTickets(Request $request)
    {
        $user = auth()->user();
        $tickets = $user->purchased_tickets;
        return response()->json(['success' => true, 'tickets' => $tickets]);
    }
}
