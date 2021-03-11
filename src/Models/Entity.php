<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Models;

use Carbon\Carbon;
use IFRS\Exceptions\MissingCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Interfaces\Recyclable;

use IFRS\Traits\Recycling;
use IFRS\Traits\ModelTablePrefix;

/**
 * Class Entity
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Currency $currency
 * @property string $name
 * @property bool $multi_currency
 * @property integer $year_start
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */
class Entity extends Model implements Recyclable
{
    use SoftDeletes;
    use Recycling;
    use ModelTablePrefix;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        
        'id',
        'parent_id',
        'currency_id',
        'name',
        'multi_currency',
        'year_start',
        'account_code',
        'destroyed_at',
        'deleted_at',
        'created_at',
        'updated_at',
        'purchase_order',
        'sales_order',
    ];

    /**
     * Entity's Reporting Currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    protected function currency()
    {
        return $this->hasOne(Currency::class);
    }

    /**
     * Instance Identifier.
     *
     * @return string
     */
    public function toString($type = false)
    {
        $classname = explode('\\', self::class);
        return $type ? array_pop($classname) . ': ' . $this->name : $this->name;
    }

    /**
     * Model's Parent Entity (if exists).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function parent()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Model's Daughter Entities (if any).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function daughters()
    {
        return $this->hasMany(Entity::class, 'parent_id', 'id');
    }

    /**
     * Users associated with the reporting Entity.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(config('ifrs.user_model'));
    }

    /**
     * Entity's Registered Currencies.
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function currencies()
    {
        return $this->hasMany(Currency::class);
    }

    /**
     * Entity's Reporting Periods.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reportingPeriods()
    {
        return $this->hasMany(ReportingPeriod::class);
    }

    /**
     * Entity attributes
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }

    /**
     * Reporting Currency Default Rate.
     *
     * @return ExchangeRate
     */
    public function getDefaultRateAttribute(): ExchangeRate
    {
        if (is_null($this->reportingCurrency)) {
            throw new MissingCurrency();
        }

        $now = Carbon::now();
        $existing = ExchangeRate::where([
            "entity_id" => $this->id,
            "currency_id" => $this->currency_id,
        ])->where("valid_from", "<=", $now)
            ->first();

        if (!is_null($existing)) {
            return $existing;
        }

        $new = new ExchangeRate([
            'valid_from' => Carbon::now(),
            'currency_id' => $this->reportingCurrency->id,
            "rate" => 1
        ]);

        $new->save();

        return $new;
    }

    /**
     * Current Reporting Period for the Entity.
     *
     * @return ReportingPeriod
     */
    public function getCurrentReportingPeriodAttribute(): ReportingPeriod
    {
        $existing = $this->reportingPeriods->where('calendar_year', date("Y"))->first();

        if (!is_null($existing)) {
            return $existing;
        }

        $new = new ReportingPeriod([
            'calendar_year' => date('Y'),
            'period_count' => count(ReportingPeriod::withTrashed()->get()) + 1,
        ]);

        $new->save();

        return $new;
    }

    /**
     * Reporting Currency for the Entity.
     *
     * @return Currency
     */
    public function getReportingCurrencyAttribute(): Currency
    {
        
        if (is_null($this->currency) && is_null($this->parent)) {
            //dd($this->currency, $this->parent);
            return new Currency();
        }

        return $this->currency; // is_null($this->parent) ? $this->currency : $this->parent->currency;
    }

    public function sites(){
        return $this->hasMany('App\Site');
    }
}
