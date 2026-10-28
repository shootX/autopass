<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPush;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Push\ApnsClient;
use App\Support\AdminAudit;
use Illuminate\Http\Request;

/** Promotion pushes, only to drivers who left "special promotions" on in the app. */
class PushBroadcastController extends Controller
{
    private const BATCH = 1000;

    public function index()
    {
        $apns = ApnsClient::settings();

        return view('admin.push.index', [
            'audience' => $this->audience()->count(),
            'apns' => ['key_id' => $apns['key_id'], 'team_id' => $apns['team_id'], 'bundle_id' => $apns['bundle_id'], 'has_key' => $apns['key'] !== ''],
            'configured' => ApnsClient::configured(),
        ]);
    }

    /** Apple push key (.p8), Key ID, Team ID and bundle id; the key is stored encrypted and never shown. */
    public function settings(Request $request)
    {
        $data = $request->validate([
            'key_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'team_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'bundle_id' => ['required', 'string', 'max:155', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'key' => ['nullable', 'string', 'max:4000'],
        ]);
        if (filled($data['key'] ?? null) && openssl_pkey_get_private(trim($data['key'])) === false) {
            return back()->withInput()->withErrors(['key' => __('admin.apns_bad_key')]);
        }
        ApnsClient::store($data);
        AdminAudit::record('push', 0, 'apns_settings', 'key_id', null, $data['key_id'], (int) $request->user()->id);

        return redirect()->route('admin.push')->with('message', __('admin.apns_saved'));
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
            ->where('user_push_tokens.kind', UserPushToken::KIND_APNS)
            ->join('users', 'users.id', '=', 'user_push_tokens.user_id')
            ->where('users.role', User::ROLE_USER)
            ->where('users.enable_push_special_promotions', true)
            ->where(fn ($q) => $q->where('users.ban', false)->orWhereNull('users.ban'));
    }
}
