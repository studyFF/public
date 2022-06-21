<?php

namespace App\Models\v1;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string name
 * @property string abbreviation
 * @property integer sort
 * @property integer state
 * @property integer is_default
 */
class Dhl extends Model
{
    use SoftDeletes;
    const DHL_IS_DEFAULT_NO = 0; //是否默认：否
    const DHL_IS_DEFAULT_YES = 1; //是否默认：是
    public static $withoutAppends = true;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
