<?php

use Domains\GuildDkp\Support\CalculateEventParticipantDkpPoints;

it('calculates fixed dkp per participant', function () {
    $amounts = CalculateEventParticipantDkpPoints::resolveAll(10, false, [
        ['character_id' => 1, 'dkp_coefficient' => 1],
        ['character_id' => 2, 'dkp_coefficient' => 2],
    ]);

    expect($amounts)->toBe([10, 20]);
});

it('returns override instead of calculated dkp', function () {
    $amounts = CalculateEventParticipantDkpPoints::resolveAll(10, false, [
        ['character_id' => 1, 'dkp_coefficient' => 1, 'dkp_points_override' => 7],
    ]);

    expect($amounts)->toBe([7]);
});

it('skips external participants without character id', function () {
    $amounts = CalculateEventParticipantDkpPoints::resolveAll(10, false, [
        ['external_name' => 'Guest'],
        ['character_id' => 1, 'dkp_coefficient' => 1],
    ]);

    expect($amounts)->toBe([null, 10]);
});

it('distributes total event dkp among participants by coefficient', function () {
    $amounts = CalculateEventParticipantDkpPoints::resolveAll(90, true, [
        ['character_id' => 1, 'dkp_coefficient' => 1],
        ['character_id' => 2, 'dkp_coefficient' => 2],
    ]);

    expect($amounts)->toBe([30, 60]);
});

it('returns null distribute amounts when sum of coefficients is zero', function () {
    $amounts = CalculateEventParticipantDkpPoints::resolveAll(90, true, [
        ['character_id' => 1, 'dkp_coefficient' => 0],
        ['character_id' => 2, 'dkp_coefficient' => 0],
    ]);

    expect($amounts)->toBe([null, null]);
});

it('uses default coefficient when value is missing', function () {
    expect(CalculateEventParticipantDkpPoints::resolve(10, null, null))->toBe(10);
});
