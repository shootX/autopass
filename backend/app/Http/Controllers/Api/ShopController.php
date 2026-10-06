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

        $vouchers = Voucher::query()
            ->with('category:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        $tickets = Ticket::query()
            ->latest('id')
            ->limit(50)
            ->get();

        $myVouchers = $user->vouchers()
            ->with('category:id,name')
            ->limit(30)
            ->get()
            ->each(function ($item) {
                $item->setAttribute('code', $item->pivot->code ?? null);
            })
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

        return response()->json([
            'success' => true,
            'vouchers' => $vouchers,
            'tickets' => $tickets,
            'my_vouchers' => $myVouchers,
            'my_tickets' => $myTickets,
        ]);
    }
}
