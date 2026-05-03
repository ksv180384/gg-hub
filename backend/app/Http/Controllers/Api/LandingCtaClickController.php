<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\StoreLandingCtaClickRequest;
use App\Http\Resources\Landing\LandingCtaClickResource;
use App\Models\LandingCtaClick;
use Illuminate\Http\JsonResponse;

class LandingCtaClickController extends Controller
{
    public function store(StoreLandingCtaClickRequest $request): JsonResponse
    {
        $ua = $request->userAgent();
        if ($ua !== null && strlen($ua) > 2000) {
            $ua = substr($ua, 0, 2000);
        }

        $click = LandingCtaClick::query()->create([
            'button' => $request->validated('button'),
            'user_agent' => $ua,
            'ip_address' => $request->ip(),
        ]);

        return (new LandingCtaClickResource($click))->response()->setStatusCode(201);
    }
}
