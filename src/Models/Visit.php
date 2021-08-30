<?php

namespace vildanbina\Laravel\VisitorTracker\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $table = 'activity_log';

    protected $guarded = [];

    protected $casts = [
        'is_ajax' => 'boolean',
        'is_login_attempt' => 'boolean',
        'is_bot' => 'boolean',
        'is_mobile' => 'boolean',
    ];
}
