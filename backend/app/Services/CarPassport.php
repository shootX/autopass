<?php

namespace App\Services;

use App\Models\UserCar;
use Illuminate\Http\Request;

/**
 * VIN and colour the app reads from the tech passport (fields E and R). The driver sees and can fix them before
 * saving; the passport photo stays on the phone and the owner's name and personal number are never sent.
 */
class CarPassport
{
    public const COLOR_MAX = 40;

    /**
     * The columns to save for the fields the request carries (an empty value clears one), or the error code of
     * the first bad value: `vin_invalid` or `color_invalid`.
     *
     * @return array{0: array<string, string|null>, 1: string|null}
     */
    public static function fields(Request $request): array
    {
        $data = [];
        if ($request->has('vin')) {
            $vin = strtoupper(preg_replace('/[\s-]+/', '', (string) $request->input('vin')));
            if ($vin !== '' && ! preg_match(UserCar::VIN_PATTERN, $vin)) {
                return [[], 'vin_invalid'];
            }
            $data['vin'] = $vin !== '' ? $vin : null;
        }
        if ($request->has('color')) {
            $color = trim(preg_replace('/\s+/', ' ', (string) $request->input('color')));
            if (mb_strlen($color) > self::COLOR_MAX) {
                return [[], 'color_invalid'];
            }
            $data['color'] = $color !== '' ? $color : null;
        }

        return [$data, null];
    }

    public static function error(string $code): array
    {
        return ['success' => false, 'error' => __('cars.'.$code), 'error_code' => $code];
    }
}
