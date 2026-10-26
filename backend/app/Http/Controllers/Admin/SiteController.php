<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteLead;
use App\Support\SitePages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Site management: the landing's texts, lists and sections, its settings and the business requests. */
class SiteController extends Controller
{
    private const MAX_ITEMS = 50;

    public function edit(string $page = 'home')
    {
        abort_unless(in_array($page, SitePages::PAGES, true), 404);

        return view('admin.site.page', [
            'page' => $page,
            'groups' => SitePages::defaults($page)['groups'],
            'texts' => SitePages::texts($page),
            'lists' => SitePages::lists($page),
            'sections' => SitePages::sections($page),
            'defaults' => SitePages::defaults($page),
        ]);
    }

    public function save(Request $request, string $page)
    {
        abort_unless(in_array($page, SitePages::PAGES, true), 404);
        // Text keys and list names hold dots ("hero.h1", "plan.l"), so they are read as array keys, never as dot paths.
        $validator = Validator::make($request->all(), [
            'texts' => 'array',
            'lists' => 'array',
            'sections' => 'array',
            'sections.*.order' => 'nullable|integer|min:1|max:99',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $defaults = SitePages::defaults($page);
        $texts = (array) $request->input('texts', []);
        $lists = (array) $request->input('lists', []);
        $data = [];

        foreach ($defaults['texts'] as $key => $default) {
            foreach (SitePages::LANGS as $lang) {
                $value = self::clean($texts[$key][$lang] ?? $default[$lang]);
                if ($value !== trim($default[$lang])) {
                    $data['texts'][$key][$lang] = $value;
                }
            }
        }

        $stored = SitePages::stored($page)['lists'] ?? [];
        foreach ($defaults['lists'] as $name => $list) {
            // A list the form did not send keeps what it had; the editor always sends each list (its empty add row).
            if (! array_key_exists($name, $lists)) {
                if (isset($stored[$name])) {
                    $data['lists'][$name] = $stored[$name];
                }

                continue;
            }
            $items = [];
            foreach (array_slice((array) $lists[$name], 0, self::MAX_ITEMS + 1) as $row) {
                if (! is_array($row) || ! empty($row['delete'])) {
                    continue;
                }
                $item = [];
                foreach (SitePages::LANGS as $lang) {
                    foreach ($list['fields'] as $field) {
                        $item[$lang][$field] = self::clean($row[$lang][$field] ?? '');
                    }
                }
                if (implode('', array_map(fn (array $fields) => implode('', $fields), $item)) !== '') {
                    $items[] = $item;
                }
            }
            $data['lists'][$name] = array_slice($items, 0, self::MAX_ITEMS);
        }

        foreach ($defaults['sections'] as $i => $id) {
            $data['sections'][$id] = [
                'visible' => $request->boolean("sections.{$id}.visible"),
                'order' => (int) $request->input("sections.{$id}.order", $i + 1),
            ];
        }

        SitePages::store($page, $data);

        return redirect()->route('admin.site.page', $page)->with('message', __('admin.site_saved'));
    }

    private static function clean(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 2000) : '';
    }

    public function reset(string $page)
    {
        abort_unless(in_array($page, SitePages::PAGES, true), 404);
        SitePages::store($page, []);

        return redirect()->route('admin.site.page', $page)->with('message', __('admin.site_reset_done'));
    }

    public function settings()
    {
        return view('admin.site.settings', ['settings' => SitePages::settings()]);
    }

    public function saveSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'price_4' => 'required|numeric|min:0|max:100000',
            'price_8' => 'required|numeric|min:0|max:100000',
            'price_12' => 'required|numeric|min:0|max:100000',
            'hours' => 'nullable|string|max:40',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:120',
            'ios_url' => 'nullable|url|max:255',
            'privacy_url' => 'nullable|url|max:255',
            'terms_url' => 'nullable|url|max:255',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = array_map(fn ($v) => trim((string) $v), $validator->validated());
        $data['branches_from_db'] = $request->boolean('branches_from_db') ? '1' : '0';
        SitePages::store('settings', $data);

        return redirect()->route('admin.site.settings')->with('message', __('admin.site_saved'));
    }

    public function leads()
    {
        $leads = SiteLead::query()->latest()->paginate(30);

        return view('admin.site.leads', compact('leads'));
    }

    public function handleLead(SiteLead $lead)
    {
        $lead->update(['handled_at' => $lead->handled_at ? null : now()]);

        return redirect()->back();
    }

    public function deleteLead(SiteLead $lead)
    {
        $lead->delete();

        return redirect()->back()->with('message', __('admin.site_lead_deleted'));
    }
}
