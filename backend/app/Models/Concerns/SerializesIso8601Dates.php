<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Serializes model dates as ISO 8601 UTC strings without microseconds
 * (e.g. "2026-08-14T09:00:00Z"), matching the API Design timestamp convention.
 */
trait SerializesIso8601Dates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::instance($date);

        return $carbon->utc()->format('Y-m-d\TH:i:s\Z');
    }
}