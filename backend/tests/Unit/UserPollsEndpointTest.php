<?php

use App\Actions\User\GetCurrentUserAction;
use App\Actions\User\UpdateUserProfileAction;
use App\Filters\CharacterFilter;
use App\Http\Controllers\Api\UserController;
use Domains\Character\Models\Character;
use Domains\Event\Actions\ListUserGuildCalendarEventsAction;
use Domains\Guild\Actions\CountUserActiveGuildApplicationsAction;
use Domains\Guild\Actions\GetUserGuildsForGameAction;
use Domains\Guild\Actions\ListUserGuildApplicationsAction;
use Domains\Poll\Actions\ListUserPollsAction;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

it('passes the game filter from the polls endpoint without querying the database', function (): void {
    $user = new User;
    $user->forceFill(['id' => 10]);

    $request = Request::create('/api/v1/user/polls', 'GET', ['game_id' => '4']);
    $request->setUserResolver(fn (): User => $user);

    $listUserPolls = Mockery::mock(ListUserPollsAction::class);
    $listUserPolls
        ->shouldReceive('__invoke')
        ->once()
        ->with(
            $user,
            Mockery::on(function (mixed $filter): bool {
                if (! $filter instanceof CharacterFilter) {
                    return false;
                }

                $query = $filter->apply(Character::query());
                $sql = strtolower(str_replace(['"', '`'], '', $query->toSql()));

                return str_contains($sql, 'game_id = ?')
                    && $query->getBindings() === [4];
            }),
        )
        ->andReturn(new Collection);

    $controller = new UserController(
        Mockery::mock(GetCurrentUserAction::class),
        Mockery::mock(UpdateUserProfileAction::class),
        Mockery::mock(GetUserGuildsForGameAction::class),
        Mockery::mock(ListUserGuildApplicationsAction::class),
        Mockery::mock(CountUserActiveGuildApplicationsAction::class),
        $listUserPolls,
        Mockery::mock(ListUserGuildCalendarEventsAction::class),
    );

    $response = $controller->polls($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['data' => []]);
});
