<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientProfile;

class ClientProfileController extends Controller
{
    public function show(User $client)
    {
        abort_unless((int) $client->role === User::ROLE_USER, 404);

        return view('admin.clients.show', (new ClientProfile($client))->data());
    }
}
