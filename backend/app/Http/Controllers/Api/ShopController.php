<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function home()
    {
        $user = auth()->user();

        // Still sold only, with how many this user owns and may still buy (owned, left).
        $vouchers = Voucher::query()
            ->onSale()
            ->with('category:id,name')
            ->latest('id')
            ->limit(50)
            ->get();
        Voucher::fillForUser($vouchers, (int) $user->id);

        $tickets = Ticket::query()
            ->onSale()
            ->withCount('users as sold_count')
            ->latest('id')
            ->limit(50)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->setAttribute('left', (int) $ticket->count > 0
                ? max(0, min(20, (int) $ticket->count - (int) $ticket->sold_count)) : 20);
            $ticket->makeHidden('sold_count');
        }

        // One row per purchase, so two of the same offer open their own codes: id is the purchase, voucher_id
        // the offer. Expired ones are in the history only.
        $myVouchers = $user->vouchers()
            ->withPivot('id', 'code')
            ->notExpired()
            ->with('category:id,name')
            ->limit(30)
            ->get()
            ->map(fn (Voucher $item) => array_merge($item->toArray(), [
                'id' => (int) $item->pivot->id,
                'voucher_id' => (int) $item->id,
                'code' => $item->pivot->code ?? null,
            ]))
            ->values();

        $myTickets = DB::table('user_tickets')
            ->join('tickets', 'tickets.id', '=', 'user_tickets.ticket_id')
            ->where('user_tickets.user_id', $user->id)
            ->groupBy('tickets.id', 'tickets.name', 'tickets.photo')
            ->orderByDesc('tickets.id')
            ->limit(30)
            ->get([
                'tickets.id',
                'tickets.id as ticket_id',
                'tickets.name',
                'tickets.photo',
                DB::raw('COUNT(*) as qty'),
            ])
            ->map(function ($row) {
                $row->photo = $row->photo ? asset($row->photo) : null;

                return $row;
            })
            ->values();

        $history = Ticket::withTrashed()->whereIn('id', $myTickets->pluck('id'))->get()->keyBy('id');
        $myTickets = $myTickets->map(fn ($row) => array_merge($history->get($row->id)?->toArray() ?? [],
            (array) $row, ['left' => 0]));

        return response()->json([
            'success' => true,
            'vouchers' => $vouchers,
            'tickets' => $tickets,
            'my_vouchers' => $myVouchers,
            'my_tickets' => $myTickets,
        ]);
    }
}
