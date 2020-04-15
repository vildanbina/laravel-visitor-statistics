<?php

namespace bexvibi\Laravel\VisitorTracker\Jobs;

use bexvibi\Laravel\VisitorTracker\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetGeoipData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    protected $visit;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Visit $visit)
    {
        $this->visit = $visit;
    }

    /**
     * Execute the job. Fetches geolocation data from the preferred driver for
     * a specific visit. Records the data to the database if the received
     * values are not excluded from being tracked in the config file.
     *
     * @return void
     */
    public function handle()
    {
        if (config('visitortracker.geoip_on')) {
            $geoip = geoip($this->visit->ip);
            $data = [
                'lat' => $geoip->getAttribute('lat') ?: null,
                'long' => $geoip->getAttribute('lon') ?: null,
                'country' => $geoip->getAttribute('country') ?: '',
                'country_code' => $geoip->getAttribute('iso_code') ?: '',
                'city' => $geoip->getAttribute('city') ?: '',
            ];

            if ($this->shouldRecordVisit($data)) {
                $this->visit->update($data);
            } else {
                $this->visit->delete();
            }

        }
    }

    /**
     * Determine if the request/visit should be recorded
     *
     * @return boolean
     */
    protected
    static function shouldRecordVisit($data)
    {
        foreach (config('visitortracker.dont_record_geoip') as $fields) {
            $conditionsMet = 0;
            foreach ($fields as $field => $value) {
                if ($data[$field] == $value) {
                    $conditionsMet++;
                }
            }

            if ($conditionsMet == count($fields)) {
                return false;
            }
        }

        return true;
    }
}
