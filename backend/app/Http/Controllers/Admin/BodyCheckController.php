<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/** cars:check-bodies from the panel, in small batches (geoapi.ge allows 15 requests a minute). */
class BodyCheckController extends Controller
{
    private const LIMIT = 10;

    public function run(Request $request)
    {
        $apply = $request->boolean('apply');
        $lock = Cache::lock('admin:cars-check-bodies', 120);
        if (! $lock->get()) {
            return redirect()->route('admin.body_types')->with('error', __('admin.body_check_busy'));
        }
        try {
            Artisan::call('cars:check-bodies', array_filter([
                '--limit' => self::LIMIT,
                '--unknown' => $request->boolean('unknown'),
                '--apply' => $apply,
            ]));
            $output = trim(Artisan::output());
        } finally {
            $lock->release();
        }
        if ($apply) {
            AdminAudit::record('cars', 0, 'check_bodies', 'apply', null, '1', (int) $request->user()->id);
        }

        return redirect()->route('admin.body_types')->with('body_check', ['apply' => $apply, 'output' => mb_substr($output, 0, 20000)]);
    }
}
