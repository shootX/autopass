<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPush;
use App\Models\User;
use App\Models\UserPushToken;
use App\Support\AdminAudit;
use Illuminate\Http\Request;

/** Promotion pushes, only to drivers who left "special promotions" on in the app. */
class PushBroadcastController extends Controller
{
    private const BATCH = 1000;

    public function index()
    {
        return view('admin.push.index', ['audience' => $this->audience()->count()]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'title_ka' => 'required|string|max:80',
            'text_ka' => 'required|string|max:300',
            'title_en' => 'nullable|string|max:80',
            'text_en' => 'nullable|string|max:300',
            'title_ru' => 'nullable|string|max:80',
            'text_ru' => 'nullable|string|max:300',
            'url' => 'nullable|url|max:255',
        ]);
        $title = $text = [];
        foreach (['ka', 'en', 'ru'] as $lang) {
            $title[$lang] = ($data['title_'.$lang] ?? null) ?: $data['title_ka'];
            $text[$lang] = ($data['text_'.$lang] ?? null) ?: $data['text_ka'];
        }

        $sent = 0;
        $this->audience()->orderBy('user_push_tokens.id')->select('user_push_tokens.push_id')
            ->chunk(self::BATCH, function ($rows) use ($title, $text, $data, &$sent) {
                $ids = $rows->pluck('push_id')->filter()->values()->all();
                if ($ids !== []) {
                    SendPush::dispatch($ids, $title, $text, filled($data['url'] ?? null) ? ['url' => $data['url']] : []);
                    $sent += count($ids);
                }
            });
        AdminAudit::record('push', 0, 'broadcast', 'title', null, $title['ka'], (int) $request->user()->id);

        return redirect()->route('admin.push')->with('message', __('admin.push_sent', ['count' => $sent]));
    }

    private function audience()
    {
        return UserPushToken::query()
            ->join('users', 'users.id', '=', 'user_push_tokens.user_id')
            ->where('users.role', User::ROLE_USER)
            ->where('users.enable_push_special_promotions', true)
            ->where(fn ($q) => $q->where('users.ban', false)->orWhereNull('users.ban'));
    }
}
