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
use Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Reports\AccountSchedule;

use IFRS\Interfaces\Assignable;
use IFRS\Interfaces\Segregatable;

use IFRS\Traits\Segregating;
use IFRS\Traits\ModelTablePrefix;

use IFRS\Exceptions\OverClearance;
use IFRS\Exceptions\SelfClearance;
use IFRS\Exceptions\NegativeAmount;
use IFRS\Exceptions\MixedAssignment;
use IFRS\Exceptions\UnpostedAssignment;
use IFRS\Exceptions\InsufficientBalance;
use IFRS\Exceptions\MissingForexAccount;
use IFRS\Exceptions\InvalidClearanceEntry;
use IFRS\Exceptions\UnclearableTransaction;
use IFRS\Exceptions\InvalidClearanceAccount;
use IFRS\Exceptions\UnassignableTransaction;
use IFRS\Exceptions\InvalidClearanceCurrency;

/**
 * Class Assignment
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Assignable $transaction
 * @property Clearable $cleared
 * @property float $amount
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */
class Assignment extends Model implements Segregatable
{
    protected $connection = 'datadb';

    use Segregating;
    use SoftDeletes;
    use ModelTablePrefix;

    /**
     * Clearable Transaction Types
     *
     * @var array
     */

    const CLEARABLES = [
        Transaction::IN,
        Transaction::BL,
        Transaction::JN
    ];

    /**
     * Assignable Transaction Types
     *
     * @var array
     */

    const ASSIGNABLES = [
        Transaction::RC,
        Transaction::PY,
        Transaction::CN,
        Transaction::DN,
        Transaction::JN,
        Transaction::IC,
        Transaction::BC,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Bulk assign a transaction to outstanding Transactions, under FIFO (First in first out) methodology
     *
     * @param Assignable $transaction
     */

    public static function bulkAssign(Assignable $transaction): void
    {

        $balance = $transaction->balance;

        $schedule = new AccountSchedule($transaction->account_id, $transaction->currency_id);
        $schedule->getTransactions();

        foreach ($schedule->transactions as $outstanding) {

            if ($outstanding->unclearedAmount > $balance) {
                $assignment = new Assignment(
                    [
                        'assignment_date' => Carbon::now(),
                        'transaction_id' => $transaction->id,
                        'cleared_id' => $outstanding->id,
                        'cleared_type' => $outstanding->cleared_type,
                        'amount' => $balance,
                    ]
                );
                $assignment->save();
                break;
            } else {
                $assignment = new Assignment(
                    [
                        'assignment_date' => Carbon::now(),
                        'transaction_id' => $transaction->id,
                        'cleared_id' => $outstanding->id,
                        'cleared_type' => $outstanding->cleared_type,
                        'amount' => $outstanding->unclearedAmount,
                    ]
                );
                $assignment->save();
                $balance -= $outstanding->unclearedAmount;
            }
        }
    }

    /**
     * Assignment Validation.
     */
    private function validate(): void
    {
        $transactionType = $this->transaction->transaction_type;
        $clearedType = $this->cleared->transaction_type;

        $transactionRate = 1; // $this->transaction->exchangeRate->rate;
        $clearedRate = 1; // $this->cleared->exchangeRate->rate;

        if (!in_array($transactionType, Assignment::ASSIGNABLES)) {
            throw new UnassignableTransaction($transactionType, Assignment::ASSIGNABLES);
        }

        // Clearable Transactions
        if (!in_array($clearedType, Assignment::CLEARABLES)) {
            throw new UnclearableTransaction($clearedType, Assignment::CLEARABLES);
        }

        if ($this->amount < 0) {
            throw new NegativeAmount("Assignment");
        }

        if ($this->cleared_id == $this->transaction_id && $this->cleared_type == Transaction::MODELNAME) {
            throw new SelfClearance();
        }

        if (!$this->transaction->is_posted || !$this->cleared->is_posted) {
            throw new UnpostedAssignment();
        }

        if ($this->cleared->account_id != $this->transaction->account_id) {
            throw new InvalidClearanceAccount();
        }

        if ($this->cleared->currency_id != $this->transaction->currency_id) {
            throw new InvalidClearanceCurrency();
        }

        if ($this->cleared->is_credited == $this->transaction->is_credited) {
            throw new InvalidClearanceEntry();
        }

        if (bccomp(strval($this->transaction->balance), strval($this->amount)) == -1) {
            Log::info(bccomp(strval($this->transaction->balance), strval($this->amount)));
            Log::info(strval($this->transaction->balance).' - '.strval($this->amount));
            throw new InsufficientBalance($transactionType, $this->transaction->balance. '|' .$this->amount, $clearedType);
        }

        if (bccomp(strval($this->cleared->amount - $this->cleared->cleared_amount), strval($this->amount)) == -1) {
            throw new OverClearance($clearedType, $this->amount);
        }

        /*
        if (bccomp($this->cleared->amount - $this->cleared->cleared_amount < $this->amount) {
            throw new OverClearance($clearedType, $this->amount);
        }
        */

        if ($transactionRate !== $clearedRate && is_null($this->forexAccount)) {
            throw new MissingForexAccount();
        }

        if ($this->cleared_type != Balance::MODELNAME && count($this->cleared->assignments) > 0) {
            throw new MixedAssignment("Assigned", "Cleared");
        }

        if (count($this->transaction->clearances) > 0) {
            throw new MixedAssignment("Cleared", "Assigned");
        }
    }

    /**
     * Instance Identifier.
     *
     * @return string
     */
    public function toString($type = false)
    {
        $classname = explode('\\', self::class);
        $description = 'Assigning ' . $this->transaction->transaction_no . ' on ' . $this->assignment_date;
        return $type ? array_pop($classname) . ': ' . $description : $description;
    }

    /**
     * Transaction to be cleared.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Transaction|Balance to be cleared.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function cleared()
    {
        return $this->morphTo();
    }

    /**
     * Account for posting Exchange Rate Differences.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function forexAccount()
    {
        return $this->hasOne(Account::class);
    }

    /**
     * Assignment attributes.
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }

    /**
     * Assignment Validation.
     */
    public function save(array $options = []): bool
    {
        $this->validate();

        return parent::save();
    }
}
