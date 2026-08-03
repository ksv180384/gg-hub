<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\GuildActivity\RecordGuildActivityAction;
use App\GuildActivityLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuildActivity\AuthenticateGuildRouletteSocketRequest;
use App\Http\Requests\GuildActivity\StoreGuildRouletteActivityRequest;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\User\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class GuildRouletteSocketController extends Controller
{
    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
        private RecordGuildActivityAction $recordGuildActivityAction,
    ) {}

    public function token(Request $request, Guild $guild): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $expiresAt = now()->addMinutes(10);
        $token = Crypt::encryptString(json_encode([
            'guild_id' => (int) $guild->id,
            'user_id' => (int) $user->id,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function authenticate(AuthenticateGuildRouletteSocketRequest $request): JsonResponse
    {
        try {
            $payload = json_decode(
                Crypt::decryptString($request->validated('token')),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|\JsonException) {
            abort(401);
        }

        $guildId = (int) ($payload['guild_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $expiresAt = (int) ($payload['expires_at'] ?? 0);

        if ($guildId <= 0 || $userId <= 0 || $expiresAt <= now()->getTimestamp()) {
            abort(401);
        }

        $guild = Guild::query()->findOrFail($guildId);
        $user = User::query()->findOrFail($userId);
        $characterIds = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('character_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        abort_if($characterIds->isEmpty(), 403);

        $permissionSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);

        return response()->json([
            'data' => [
                'guild_id' => (int) $guild->id,
                'user_id' => (int) $user->id,
                'character_ids' => $characterIds,
                'can_manage' => $permissionSlugs->contains('upravlenie-ruletkoi'),
            ],
        ]);
    }

    public function audit(StoreGuildRouletteActivityRequest $request): JsonResponse
    {
        $this->ensureInternalRequest($request);

        $data = $request->validated();
        $guild = Guild::query()->findOrFail((int) $data['guild_id']);
        $user = User::query()->findOrFail((int) $data['user_id']);

        $isMember = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
        abort_unless($isMember, 403);

        $action = (string) $data['action'];
        ($this->recordGuildActivityAction)(
            $guild,
            $user,
            GuildActivityLog::CATEGORY_ROULETTE,
            $action,
            $this->description($action),
            metadata: $data['metadata'] ?? [],
        );

        return response()->json(['ok' => true]);
    }

    private function ensureInternalRequest(Request $request): void
    {
        $expected = (string) config('services.socket_server.internal_token', '');
        $received = (string) $request->header('X-Socket-Internal-Token', '');

        abort_if($expected === '' || $received === '' || ! hash_equals($expected, $received), 401);
    }

    private function description(string $action): string
    {
        return match ($action) {
            'roulette.entries_updated' => 'Обновлён список участников рулетки.',
            'roulette.entry_added' => 'Участник добавлен в рулетку.',
            'roulette.entry_removed' => 'Участник удалён из рулетки.',
            'roulette.enrollment_opened' => 'Открыт набор участников рулетки.',
            'roulette.enrollment_closed' => 'Закрыт набор участников рулетки.',
            'roulette.elimination_mode_enabled' => 'Включён режим выбывания.',
            'roulette.elimination_mode_disabled' => 'Выключен режим выбывания.',
            'roulette.dkp_coefficients_enabled' => 'Включены коэффициенты ДКП рулетки.',
            'roulette.dkp_coefficients_disabled' => 'Выключены коэффициенты ДКП рулетки.',
            'roulette.dkp_coefficients_updated' => 'Изменены коэффициенты ДКП участников рулетки.',
            'roulette.external_dkp_coefficients_updated' => 'Изменены коэффициенты ДКП внешних участников.',
            'roulette.spin_started' => 'Запущено вращение рулетки.',
            default => 'Выполнено действие в рулетке.',
        };
    }
}
