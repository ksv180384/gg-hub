<?php

use App\Http\Controllers\Api\ConstantPartyStorageController;
use App\Http\Controllers\Api\GuildAuctionController;
use App\Http\Requests\Character\AdminCharacterIndexRequest;
use Domains\Character\Actions\ListAdminCharactersAction;
use Illuminate\Support\Facades\Validator;

function endpointMethodSource(string $class, string $method): string
{
    $reflection = new ReflectionMethod($class, $method);
    $lines = file($reflection->getFileName(), FILE_IGNORE_NEW_LINES);

    return implode("\n", array_slice(
        $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    ));
}

it('accepts only supported admin character sorting values without a database', function (): void {
    $rules = (new AdminCharacterIndexRequest)->rules();

    expect(Validator::make(['sort' => 'server', 'direction' => 'desc'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['sort' => 'password', 'direction' => 'sideways'], $rules)->fails())->toBeTrue();
});

it('keeps configurable endpoint sorting whitelisted and deterministic', function (
    string $class,
    string $method,
    array $expectedSource,
): void {
    $source = endpointMethodSource($class, $method);

    foreach ($expectedSource as $fragment) {
        expect($source)->toContain($fragment);
    }
})->with([
    'admin characters' => [
        ListAdminCharactersAction::class,
        '__invoke',
        [
            "'name' => 'characters.name'",
            "'email' => 'users.email'",
            "'game' => 'games.name'",
            "'server' => 'servers.name'",
            '->orderBy($sortColumns[$sort], $direction)',
            "->orderBy('characters.id')",
        ],
    ],
    'guild auction history' => [
        GuildAuctionController::class,
        'history',
        [
            "'sort' => ['nullable', 'in:asc,desc']",
            "->orderBy('closed_at', \$sort)",
            "->orderBy('id', \$sort)",
        ],
    ],
    'constant party storage logs' => [
        ConstantPartyStorageController::class,
        'logs',
        [
            "'sort' => ['nullable', 'in:asc,desc']",
            "->orderBy('created_at', \$sort)",
            "->orderBy('id', \$sort)",
        ],
    ],
]);
