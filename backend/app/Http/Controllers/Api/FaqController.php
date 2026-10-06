<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    public function faq(Request $request)
    {
        try {
            $list = Cache::rememberForever('faq', function () {
                return Faq::all();
            });
            $list = $list->map(function ($item) {
                return [
                    'id' => $item->id,
                    'question' => $item->question,
                    'answer' => $item->answer,
                ];
            });

            return response()->json([
                'success' => true,
                'faq' => $list
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка'
            ], 400);
        }
    }
}
