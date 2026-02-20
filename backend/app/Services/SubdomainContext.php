<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Http\Request;

class SubdomainContext
{
    public const ADMIN_SUBDOMAIN = 'admin';

    /**
     * Текущий субдомен из запроса (без точки и домена).
     * Например: admin.gg-hub.local → "admin", aion2.gg-hub.ru → "aion2".
     * Поддерживаются и локальный (gg-hub.local), и прод (gg-hub.ru) домены.
     * Учитывает X-Site-Host от фронта (для dev через прокси).
     */
    public function getSubdomain(Request $request): ?string
    {
        $host = $request->header('X-Site-Host') ?? $request->getHost();
        $host = preg_replace('/:\d+$/', '', (string) $host); // убрать порт
        $domains = config('app.domains', ['gg-hub.local', 'gg-hub.ru']);

        foreach ($domains as $domain) {
            $domain = (string) $domain;
            if ($domain === '') {
                continue;
            }
            if ($host === $domain) {
                return null;
            }
            $suffix = '.' . $domain;
            if (str_ends_with($host, $suffix)) {
                return strtolower((string) substr($host, 0, -strlen($suffix)));
            }
        }

        return null;
    }

    /**
     * true, если запрос с субдомена admin (админский режим).
     */
    public function isAdmin(Request $request): bool
    {
        return $this->getSubdomain($request) === self::ADMIN_SUBDOMAIN;
    }

    /**
     * Если субдомен = slug игры — возвращает игру, иначе null.
     */
    public function getGameBySubdomain(Request $request): ?Game
    {
        $sub = $this->getSubdomain($request);
        if ($sub === null || $sub === self::ADMIN_SUBDOMAIN) {
            return null;
        }

        return Game::query()->where('slug', $sub)->where('is_active', true)->first();
    }

    /**
     * Контекст для API: admin | game | main.
     * game — когда субдомен совпадает со слагом игры.
     */
    public function getContext(Request $request): array
    {
        $sub = $this->getSubdomain($request);

        if ($sub === self::ADMIN_SUBDOMAIN) {
            return [
                'mode' => 'admin',
                'subdomain' => $sub,
                'game' => null,
            ];
        }

        if ($sub !== null) {
            $game = Game::query()->where('slug', $sub)->where('is_active', true)->first();
            if ($game) {
                return [
                    'mode' => 'game',
                    'subdomain' => $sub,
                    'game' => $game,
                ];
            }
        }

        return [
            'mode' => 'main',
            'subdomain' => null,
            'game' => null,
        ];
    }
}
