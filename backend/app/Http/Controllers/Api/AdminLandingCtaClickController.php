<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landing\LandingCtaClickStatsResource;
use App\Models\LandingCtaClick;
use Illuminate\Support\Carbon;

class AdminLandingCtaClickController extends Controller
{
    /**
     * Сводка по кликам «Начать бесплатно» / «Создать аккаунт» на главной.
     */
    public function stats(): LandingCtaClickStatsResource
    {
        $counts = LandingCtaClick::query()
            ->selectRaw('button, COUNT(*) as c')
            ->groupBy('button')
            ->pluck('c', 'button');

        $startFree = (int) ($counts['start_free'] ?? 0);
        $createAccount = (int) ($counts['create_account'] ?? 0);
        $total = $startFree + $createAccount;

        $lastRaw = LandingCtaClick::query()->max('created_at');

        return new LandingCtaClickStatsResource([
            'total' => $total,
            'start_free' => $startFree,
            'create_account' => $createAccount,
            'last_click_at' => $lastRaw !== null
                ? Carbon::parse($lastRaw)->toIso8601String()
                : null,
        ]);
    }
}
