<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the configured assignee / agent column filter to a user query.
 */
class AssigneeFilter
{

    public static function apply(Builder $query): Builder
    {
        $column = config('creators-ticketing.assignee_filter_column');
        $values = config('creators-ticketing.assignee_filter_values');

        if (empty($column) || empty($values)) {
            return $query;
        }

        return $query->whereIn($column, (array) $values);
    }

    public static function active(): bool
    {
        return !empty(config('creators-ticketing.assignee_filter_column'))
            && !empty(config('creators-ticketing.assignee_filter_values'));
    }
}
