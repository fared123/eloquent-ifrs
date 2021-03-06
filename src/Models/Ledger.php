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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Interfaces\Segregatable;

use IFRS\Traits\Segregating;
use IFRS\Traits\ModelTablePrefix;

use Illuminate\Support\Facades\Auth;

/**
 * Class Ledger
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Transaction $transaction
 * @property Vat $vat
 * @property Account $postAccount
 * @property Account $folioAccount
 * @property LineItem $lineItem
 * @property Carbon $postingDate
 * @property string $entryType
 * @property float $amount
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */
class Ledger extends Model implements Segregatable
{
    protected $connection = 'datadb';

    use Segregating;
    use SoftDeletes;
    use ModelTablePrefix;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Create VAT Ledger entries for the Transaction LineItem.
     *
     * @param LineItem    $lineItem
     * @param Transaction $transaction
     *
     * @return void
     */
    private static function postVat(LineItem $lineItem, Transaction $transaction): void
    {
        if($lineItem->sku == '-PORA-'){
            $amount = floatval($lineItem->narration);
        }else{
            $amount = ($lineItem->vat_inclusive ?  $lineItem->amount - ($lineItem->amount / (1 + ($lineItem->vat->rate / 100))) : $lineItem->amount * $lineItem->vat->rate / 100);
        }

        $post = new Ledger();
        $folio = new Ledger();

        if ($transaction->is_credited) {
            $post->entry_type = Balance::CREDIT;
            $folio->entry_type = Balance::DEBIT;
        } else {
            $post->entry_type = Balance::DEBIT;
            $folio->entry_type = Balance::CREDIT;
        }

        // identical double entry data
        $post->transaction_id = $folio->transaction_id = $transaction->id;
        $post->posting_date = $folio->posting_date = $transaction->transaction_date;
        $post->line_item_id = $folio->line_item_id = $lineItem->id;
        $post->vat_id = $folio->vat_id = $lineItem->vat_id;
        $post->amount = $folio->amount = $amount * $lineItem->quantity; //  * $transaction->exchangeRate->rate

        // different double entry data
        $post->post_account = $folio->folio_account = $lineItem->vat_inclusive ? $lineItem->account_id : $transaction->account_id;
        $post->folio_account = $folio->post_account = $lineItem->vat->account_id;

        $post->user_id = $folio->user_id = Auth::id();

        $post->save();
        $folio->save();
        
    }

    /**
     * Create Ledger entries for the Transaction.
     *
     * @param Transaction $transaction
     */
    public static function post(Transaction $transaction): void
    {
        //Remove current ledgers if any prior to creating new ones (prevents bypassing Posted Transaction Exception)
        $transaction->ledgers()->delete();

        foreach ($transaction->getLineItems() as $lineItem) {
            $post = new Ledger();
            $folio = new Ledger();

            if ($transaction->is_credited) {
                $post->entry_type = Balance::CREDIT;
                $folio->entry_type = Balance::DEBIT;
            } else {
                $post->entry_type = Balance::DEBIT;
                $folio->entry_type = Balance::CREDIT;
            }

            // identical double entry data
            $post->transaction_id = $folio->transaction_id = $transaction->id;
            $post->posting_date = $folio->posting_date = $transaction->transaction_date;
            $post->line_item_id = $folio->line_item_id = $lineItem->id;
            $post->vat_id = $folio->vat_id = $lineItem->vat_id;
            $post->amount = $folio->amount = $lineItem->amount * $lineItem->quantity;

            // $post->amount = $folio->amount = $lineItem->amount * $transaction->exchangeRate->rate * $lineItem->quantity;

            // different double entry data
            $post->post_account = $folio->folio_account = $transaction->account_id;
            $post->folio_account = $folio->post_account = $lineItem->account_id;

            $post->user_id = $folio->user_id = Auth::id();

            $post->save();
            $folio->save();
            $transaction->amount += $lineItem->amount;

            if ($lineItem->vat->rate > 0) {
                Ledger::postVat($lineItem, $transaction);
            }

            // reload ledgers to reflect changes
            $transaction->load('ledgers');
        }
    }

    /**
     * Ledger attributes.
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }

    /**
     * Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Ledger Post Account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function postAccount()
    {
        return $this->hasOne(Account::class, 'id', 'post_account');
    }

    /**
     * Ledger Folio Account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function folioAccount()
    {
        return $this->hasOne(Account::class, 'id', 'folio_account');
    }

    /**
     * Ledger LineItem.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lineItem()
    {
        return $this->belongsTo(LineItem::class, 'line_item_id', 'id');
    }

    /**
     * Hash Ledger contents
     *
     * @return string
     */
    public function hashed()
    {
        $ledger = [];

        $ledger[] = $this->entity_id;
        $ledger[] = $this->transaction_id;
        $ledger[] = $this->vat_id;
        $ledger[] = $this->post_account;
        $ledger[] = $this->folio_account;
        $ledger[] = $this->line_item_id;
        $ledger[] = is_string($this->posting_date) ? $this->posting_date : $this->posting_date->format('Y-m-d H:i:s');
        $ledger[] = $this->entry_type;
        $ledger[] = $this->amount;
        $ledger[] = $this->created_at;

        $previousLedgerId = $this->id - 1;
        $previousLedger = Ledger::find($previousLedgerId);
        $previousHash = is_null($previousLedger) ? env('APP_KEY', 'test application key') : $previousLedger->hash;
        $ledger[] = $previousHash;

        return utf8_encode(implode($ledger));
    }

    /**
     * Add Ledger hash.
     */
    public function save(array $options = []): bool
    {
        parent::save();

        $this->hash = password_hash(
            $this->hashed(),
            config('ifrs')['hashing_algorithm']
        );

        return parent::save();
    }

    /**
     * Get Account's contribution to the Transaction total amount.
     *
     * @param Account $account
     * @param int     $transactionId
     *
     * @return float
     */
    public static function contribution(Account $account, int $transactionId): float
    {
        $debits = Ledger::where([
            "post_account" => $account->id,
            "entry_type" => Balance::DEBIT,
            "transaction_id" => $transactionId,
        ])->sum('amount');

        $credits = Ledger::where([
            "post_account" => $account->id,
            "entry_type" => Balance::CREDIT,
            "transaction_id" => $transactionId,
        ])->sum('amount');

        return $debits - $credits;
    }

    /**
     * Get Account's balance as at the given date.
     *
     * @param Account $account
     * @param Carbon  $startDate
     * @param Carbon  $endDate
     *
     * @return float
     */
    public static function balance(Account $account, Carbon $startDate, Carbon $endDate): float
    {
        /*
        $debits = Ledger::where([
            "post_account" => $account->id,
            "entry_type" => Balance::DEBIT,
        ])->where("posting_date", ">=", $startDate)
            ->where("posting_date", "<=", $endDate)
            ->sum('amount');

        $credits = Ledger::where([
            "post_account" => $account->id,
            "entry_type" => Balance::CREDIT,
        ])->where("posting_date", ">=", $startDate)
            ->where("posting_date", "<=", $endDate)
            ->sum('amount');
            */

        $baseQuery = Ledger::where("post_account", $account->id)
        ->where("posting_date", ">=", $startDate)
            ->where("posting_date", "<=", $endDate);

            $cloneQuery = clone $baseQuery;

        $debits = $baseQuery->where("entry_type", Balance::DEBIT)
        ->sum('amount');

        $credits = $cloneQuery->where("entry_type", Balance::CREDIT)
        ->sum('amount');

        return $debits - $credits;
    }
}
