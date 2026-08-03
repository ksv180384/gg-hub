<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GuildAuctionLotFilter extends Filter
{
    public const KEYS_TO_DATE = ['date_from', 'date_to'];

    protected function dateFrom(CarbonImmutable $value): Builder
    {
        return $this->builder->where('closed_at', '>=', $value->startOfDay());
    }

    protected function dateTo(CarbonImmutable $value): Builder
    {
        return $this->builder->where('closed_at', '<=', $value->endOfDay());
    }
}
