<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/** Marketing timestamps have their own UTC contract; the rest of the app keeps its timezone. */
abstract class MarketingModel extends Model
{
    protected function asDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            return Date::instance($value)->utc();
        }

        return is_numeric($value) ? Date::createFromTimestamp($value, 'UTC') : Date::parse($value, 'UTC');
    }

    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->format($this->getDateFormat());
    }

    public function freshTimestamp()
    {
        return Date::now('UTC');
    }
}
