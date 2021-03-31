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

use IFRS\Exceptions\NegativeAmount;
use IFRS\Exceptions\PostedTransaction;

/**
 * Class LineItem
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Transaction $transaction
 * @property Vat $vat
 * @property Account $account
 * @property Carbon $date
 * @property int $quantity
 * @property float $amount
 * @property bool $vat_inclusive
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */
class LineItem extends Model implements Recyclable, Segregatable
{
    protected $connection = 'datadb';
    
    use Segregating;
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
        'entity_id',
        'account_id',
        'vat_account_id',
        'transaction_id',
        'vat_id',
        'order_item_id',
        'sku',
        'narration',
        'amount',
        'quantity',
        'vat_inclusive',
        'destroyed_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Instance Identifier.
     *
     * @return string
     */
    public function toString($type = false)
    {
        $classname = explode('\\', self::class);
        $description = $this->account->toString() . ' for ' . $this->amount * $this->quantity;
        return $type ? array_pop($classname) . ': ' . $description : $description;
    }

    /**
     * LineItem Ledgers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ledgers()
    {
        return $this->hasMany(Ledger::class, 'line_item_id', 'id');
    }

    /**
     * LineItem Transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * LineItem Account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * LineItem VAT.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vat()
    {
        return $this->belongsTo(Vat::class);
    }

    /**
     * LineItem attributes.
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }

    /**
     * Validate LineItem.
     */
    public function save(array $options = []): bool
    {

        if ($this->amount < 0) {
            throw new NegativeAmount("LineItem");
        }

        if (!is_null($this->transaction) && count($this->transaction->ledgers) > 0 && $this->isDirty()) {
            throw new PostedTransaction("change a LineItem of");
        }

        return parent::save();
    }
}
