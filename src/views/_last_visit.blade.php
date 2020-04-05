@if (!isset($hideLastVisitDatetime))
    {{ \Carbon\Carbon::parse($visit->created_at)
        ->tz(config('app.timezone', 'UTC'))
        ->format(default_date_time_format()) }}<br>
@endif

@include('visitstats::_visitor')