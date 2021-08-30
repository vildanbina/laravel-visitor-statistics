<?php

namespace vildanbina\Laravel\VisitorTracker;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class VisitStats
{
    /**
     * The SELECT part of the SQL query
     *
     * @var string
     */
    protected $sqlSelect = '';

    /**
     * The GROUP BY part of the SQL query
     *
     * @var string
     */
    protected $sqlGroupBy = '';

    /**
     * Array of WHERE clauses
     *
     * @var array
     */
    protected $where = [];

    /**
     * Array of ORDER BY clauses
     *
     * @var array
     */
    protected $orderBy = [];

    /**
     * The LIMIT/OFFSET part of the SQL query
     *
     * @var string
     */
    protected $sqlLimitOffset = '';

    /**
     * The rest of the SQL query
     *
     * @var string
     */
    protected $sql = '';

    /**
     * A field to group the results by
     *
     * @var string
     */
    protected $groupBy;

    /**
     * Adds routes to the statistics pages
     *
     * @return void
     */
    public static function routes()
    {
        // Summary
        Route::get('/stats', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@summary')->name('visitortracker.summary');

        // Visits
        Route::get('/stats/all', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@allRequests')->name('visitortracker.all_requests');
        Route::get('/stats/visits', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@visits')->name('visitortracker.visits');
        Route::get('/stats/ajax', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@ajaxRequests')->name('visitortracker.ajax_requests');
        Route::get('/stats/bots', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@bots')->name('visitortracker.bots');
        Route::get('/stats/login-attempts', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@loginAttempts')->name('visitortracker.login_attempts');

        // Grouped visits
        Route::get('/stats/countries', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@countries')->name('visitortracker.countries');
        Route::get('/stats/os', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@os')->name('visitortracker.os');
        Route::get('/stats/browsers', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@browsers')->name('visitortracker.browsers');
        Route::get('/stats/languages', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@languages')->name('visitortracker.languages');
        Route::get('/stats/unique', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@unique')->name('visitortracker.unique');
        Route::get('/stats/users', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@users')->name('visitortracker.users');
        Route::get('/stats/urls', '\vildanbina\Laravel\VisitorTracker\Controllers\StatisticsController@urls')->name('visitortracker.urls');
    }

    /**
     * Initializes a query builder
     *
     * @return self
     */
    public static function query()
    {
        return new Self;
    }

    /**
     * Exclude rows where certain boolean fields are equal to true
     *
     * @param string $fields
     * @return $this
     */
    public function except($fields)
    {
        if (in_array('login_attempts', $fields)) {
            $this->where('v.is_login_attempt', '!=', true);
        }

        if (in_array('bots', $fields)) {
            $this->where('v.is_bot', '!=', true);
        }

        if (in_array('ajax', $fields)) {
            $this->where('v.is_ajax', '!=', true);
        }

        return $this;
    }

    /**
     * Adds an item to the $where array
     *
     * @param string $field
     * @param string $symbol
     * @param string $value
     * @return $this
     */
    public function where($field, $symbol, $value)
    {
        array_push($this->where, [$field, $symbol, $value]);

        return $this;
    }

    /**
     * Filter results by the 'created_at' field to fetch records between 2 dates
     *
     * @param Carbon\Carbon $from
     * @param Carbon\Carbon $to
     * @return $this
     */
    public function period(Carbon $from = null, Carbon $to = null)
    {
        if ($from) {
            $this->where('created_at', '>=', $from);
        }

        if ($to) {
            $this->where('created_at', '<=', $to);
        }

        return $this;
    }

    /**
     * Returns paginated query results using Laravel's LengthAwarePaginator
     *
     * @param integer $perPage
     * @return Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage)
    {
        $countable = clone $this;

        $totalCount = $countable->count();

        $page = Paginator::resolveCurrentPage();
        $offset = ($page * $perPage) - $perPage;

        $this->sqlLimitOffset = " LIMIT {$perPage} OFFSET {$offset}";

        $results = $this->get();

        return new LengthAwarePaginator($results, $totalCount, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath()
        ]);
    }

    /**
     * Returns the count of rows from the query held in the query builder at the moment
     *
     * @return integer
     */
    public function count()
    {
        if ($this->groupBy) {
            $this->visits();
            $this->sqlSelect = "SELECT COUNT(DISTINCT {$this->groupBy}) AS total";
            $this->sqlGroupBy = '';
            $this->orderBy = [];
        } else {
            $this->sqlSelect = 'SELECT COUNT(*) AS total';
        }

        return intval($this->get()[0]->total);
    }

    /**
     * Forms a basic query to fetch all the fields from the activity_log table
     *
     * @return void
     */
    public function visits()
    {
        $this->sqlSelect = 'SELECT v.*';

        $this->sql = ' FROM activity_log v';
        return $this;
    }

    /**
     * Executes the query from the query builder and returns the results
     *
     * @return array
     */
    public function get()
    {
        $results = DB::select(DB::raw($this->sql()));

        return $results;
    }

    /**
     * Returns the final SQL concatenated from multiple parts
     *
     * @return string
     */
    public function sql()
    {
        return $this->sqlSelect
            . $this->sql
            . $this->sqlWhere()
            . $this->sqlGroupBy
            . $this->sqlOrderBy()
            . $this->sqlLimitOffset;
    }

    /**
     * Returns a WHERE SQL clause formed from the $where array
     *
     * @return string
     */
    protected function sqlWhere()
    {
        if (!count($this->where)) {
            return '';
        }

        $sql = ' WHERE';

        foreach ($this->where as $key => $value) {
            if ($key > 0) {
                $sql .= ' AND';
            }
            $sql .= " {$value[0]} {$value[1]} '{$value[2]}'";
        }
        return $sql;
    }

    /**
     * Returns an ORDER BY SQL clause formed from the $orderBy array
     *
     * @return string
     */
    protected function sqlOrderBy()
    {
        if (!count($this->orderBy)) {
            return '';
        }

        $sql = ' ORDER BY';

        foreach ($this->orderBy as $key => $value) {
            if ($key > 0) {
                $sql .= ',';
            }

            $sql .= " {$value[0]} {$value[1]}";
        }

        return $sql;
    }

    /**
     * Adds a query to the builder to fetch additional data from the users table
     * along with the visits
     *
     * @return $this
     */
    public function withUsers()
    {
        if ($table = config('visitortracker.users_table')) {
            $this->sqlSelect .= ', users.email AS user_email, users.avatar as user_avatar, users.name as user_name';

            $this->sql .= "
                LEFT JOIN {$table} users
                ON users.id = v.user_id
            ";
        }

        return $this;
    }

    /**
     * Orders the results by id DESC to get the latest visits first
     *
     * @return void
     */
    public function latest()
    {
        return $this->orderBy('v.id', 'DESC');
    }

    /**
     * Adds an item to the $orderBy array
     *
     * @param string $field
     * @param string $direction
     * @return $this
     */
    public function orderBy($field, $direction = 'ASC')
    {
        array_push($this->orderBy, [$field, $direction]);

        return $this;
    }

    /**
     * Return only login attempts
     *
     * @return $this
     */
    public function loginAttempts()
    {
        return $this->where('is_login_attempt', '=', true);
    }

    /**
     * Return only visits from bots/crawlers
     *
     * @return $this
     */
    public function bots()
    {
        return $this->where('is_bot', '=', true);
    }

    /**
     * Return only ajax requests
     *
     * @return $this
     */
    public function ajax()
    {
        return $this->where('is_ajax', '=', true);
    }

    /**
     * Return only unique (by ip) visitors
     *
     * @return $this
     */
    public function unique()
    {
        return $this->groupBy('ip');
    }

    /**
     * Adds SQL to the query to group the results by a field, fetching the latest row
     * from each group and counting the number of rows in each group
     *
     * @param string $field
     * @return $this
     */
    public function groupBy($field)
    {
        $this->groupBy = $field;

        $this->sqlSelect .= ', v2.visits_count, v2.visitors_count';

        $where = str_replace('v.', '', $this->sqlWhere());

        $this->sql .= "
            JOIN 
                (
                    SELECT 
                        {$field},
                        MAX(id) AS max_id,
                        COUNT(*) AS visits_count,
                        COUNT(DISTINCT ip) AS visitors_count
                    FROM activity_log
                    
                    {$where}
                    GROUP BY {$field}
                ) v2
                ON v2.{$field} = v.{$field}
                AND v2.max_id = v.id
        ";

        $this->sqlGroupBy = " GROUP BY v2.{$field}";

        return $this;
    }
}
