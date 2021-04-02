<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Interfaces\Recyclable;
use IFRS\Interfaces\Segregatable;

use IFRS\Traits\Recycling;
use IFRS\Traits\Segregating;
use IFRS\Traits\ModelTablePrefix;

/**
 * Class ExchangeRate
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Currency $currency
 * @property Carbon $valid_from
 * @property Carbon $valid_to
 * @property float $rate
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */
class ExchangeRate extends Model implements Segregatable, Recyclable
{
    protected $connection = 'ifrs_dms';

    use Segregating;
    use SoftDeletes;
    use Recycling;
    use ModelTablePrefix;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Instance Identifier.
     *
     * @return string
     */
    public function toString($type = false)
    {
        $classname = explode('\\', self::class);
        $description = number_format($this->rate, 2) . ' for ' . $this->currency->toString() . ' from ' . $this->valid_from->toDateString();
        return $type ? array_pop($classname) . ': ' . $description : $description;
    }

    /**
     * Exchange Rate Currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * ExchangeRate attributes
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }

    /**
     * ExchangeRate Validation.
     */
    public function save(array $options = []): bool
    {
        if (!is_null($this->rate)) {
            $this->rate = abs($this->rate);
        }

        return parent::save();
    }
}
