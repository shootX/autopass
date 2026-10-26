<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteLead;
use App\Support\SitePages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Public endpoints of the marketing site (lending/): its content and the business request form. */
class SiteController extends Controller
{
    public function page(string $page): JsonResponse
    {
        if (! in_array($page, SitePages::PAGES, true)) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        return response()->json(SitePages::payload($page))->header('Cache-Control', 'public, max-age=60');
    }

    public function lead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\s-]{6,32}$/'],
            'lang' => 'nullable|in:ka,en,ru',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        SiteLead::create($validator->validated() + ['ip' => $request->ip()]);

        return response()->json(['success' => true]);
    }
}
