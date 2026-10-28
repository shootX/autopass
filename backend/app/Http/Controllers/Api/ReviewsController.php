<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewsController extends Controller
{
    public function myReviews(Request $request)
    {
        try {
            $user = auth()->user();
            $reviews = $user->reviews()->get();

            return response()->json(['success' => true, 'reviews' => $reviews]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => __('reviews.error')], 500);
        }
    }

    public function addReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'car_wash_id' => ['required', 'integer', 'exists:car_washes,id'],
            'stars' => ['required', 'integer', 'between:1,5'],
            'text' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $user = auth()->user();

            if ($user->reviews()->where('car_wash_id', $request->car_wash_id)->exists()) {
                return response()->json(['success' => false, 'error' => __('reviews.already')], 422);
            }

            $user->reviews()->create([
                'car_wash_id' => $request->car_wash_id,
                'stars' => $request->stars,
                'text' => (string) $request->input('text', ''),
            ]);

            return response()->json(['success' => true, 'message' => __('reviews.added')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => __('reviews.error')], 500);
        }
    }
}
