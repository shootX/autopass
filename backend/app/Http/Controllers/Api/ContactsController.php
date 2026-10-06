<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactsController extends Controller
{
    public function contacts(Request $request)
    {
        try {
            $contacts = Cache::rememberForever('contacts_first', function () {
                return Contact::first();
            });
            return response()->json(['success' => true, 'contacts' => $contacts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Ошибка']);
        }
    }
}
