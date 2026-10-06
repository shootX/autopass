<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CreatedTicketsExport;
use App\Exports\PartnersExport;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new CreatedTicketsExport(),
                'tickets.xlsx'
            );
        }
        $tickets = Ticket::paginate(10);
        return view('admin.tickets.index', compact('tickets'));
    }

    public function addPage(Request $request)
    {
        return view('admin.tickets.add');
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric',
            'date_to' => 'required|date',
            'count' => 'nullable|numeric',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = '/storage/'.$request->file('photo')->store('tickets', 'public');
        }

        $data = $validate->validated();

        if($data["count"] == null)
        {
            $data["count"] = 0;
        }

        $data['photo'] = $photoPath;

        Ticket::create($data);

        return redirect()->route('admin.tickets')->with('message', __('admin.ticket_created'));

    }

    public function delete(Request $request, Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->back()->with('message', __('admin.ticket_deleted'));
    }

    public function export(Request $request, Ticket $ticket)
    {
        $ticket->load('users');
        $users = $ticket->users;


        return Excel::download(
            new class($ticket->users) implements FromCollection, WithHeadings, WithMapping {

                public function __construct(private $items) {}

                public function collection()
                {
                    return $this->items;
                }

                public function headings(): array
                {
                    return [__('admin.user'), __('admin.datetime')];
                }

                public function map($item): array
                {
                    return [
                        $item->user->name,
                        Carbon::parse($item->created_at)->format('d.m.Y H:i'),
                    ];
                }

            },
            'users.xlsx'
        );
    }


    public function edit(Request $request, Ticket $ticket)
    {
        return view('admin.tickets.edit', compact('ticket'));
    }

    public function save(Request $request, Ticket $ticket)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'photo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric',
            'date_to' => 'required|date',
            'count' => 'nullable|numeric',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = '/storage/'.$request->file('photo')->store('tickets', 'public');
        }

        $data = $validate->validated();

        if($data["count"] == null)
        {
            $data["count"] = 0;
        }

        if($photoPath == null)
        {
            unset($data['photo']);
        } else {
            $data['photo'] = $photoPath;
        }

        $ticket->update($data);

        return redirect()->route('admin.tickets')->with('message', __('admin.ticket_updated'));

    }


}
