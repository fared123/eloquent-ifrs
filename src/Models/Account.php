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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Interfaces\Recyclable;
use IFRS\Interfaces\Segregatable;

use IFRS\Traits\Recycling;
use IFRS\Traits\Segregating;
use IFRS\Traits\ModelTablePrefix;

use IFRS\Exceptions\MissingAccountType;
use IFRS\Exceptions\HangingTransactions;
use IFRS\Exceptions\InvalidCategoryType;

use Session;
use Config;

/**
 * Class Account
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Category $category
 * @property Currency $currency
 * @property int|null $code
 * @property string $name
 * @property string $description
 * @property string $account_type
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 * @property float $openingBalance
 * @property float $currentBalance
 * @property float $closingBalance
 */
class Account extends Model implements Recyclable, Segregatable
{
    protected $connection = 'datadb';

    use Segregating;
    use SoftDeletes;
    use Recycling;
    use ModelTablePrefix;

    /**
     * Account Type.
     *
     * @var string
     */

    const NON_CURRENT_ASSET = 'NON_CURRENT_ASSET';
    const CONTRA_ASSET = 'CONTRA_ASSET';
    const INVENTORY = 'INVENTORY';
    const BANK = 'BANK';
    const CURRENT_ASSET = 'CURRENT_ASSET';
    const RECEIVABLE = 'RECEIVABLE';
    const NON_CURRENT_LIABILITY = 'NON_CURRENT_LIABILITY';
    const CONTROL = 'CONTROL';
    const CURRENT_LIABILITY = 'CURRENT_LIABILITY';
    const PAYABLE = 'PAYABLE';
    const EQUITY = 'EQUITY';
    const OPERATING_REVENUE = 'OPERATING_REVENUE';
    const OPERATING_EXPENSE = 'OPERATING_EXPENSE';
    const NON_OPERATING_REVENUE = 'NON_OPERATING_REVENUE';
    const DIRECT_EXPENSE = 'DIRECT_EXPENSE';
    const OVERHEAD_EXPENSE = 'OVERHEAD_EXPENSE';
    const OTHER_EXPENSE = 'OTHER_EXPENSE';
    const RECONCILIATION = 'RECONCILIATION';

    /**
     * Purchaseable Account Types
     *
     * @var array
     */

    const PURCHASABLES = [
        Account::OPERATING_EXPENSE,
        Account::DIRECT_EXPENSE,
        Account::OVERHEAD_EXPENSE,
        Account::OTHER_EXPENSE,
        Account::NON_CURRENT_ASSET,
        Account::CURRENT_ASSET,
        Account::INVENTORY
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'entity_id',
        'category_id',
        'currency_id',
        'client_id',
        'code',
        'name',
        'description',
        'account_type',
        'payment',
        'destroyed_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public function __construct(){

        parent::__construct();
        dd(session()->all(), config('database.connections.datadb'), Auth::user());
        //Config::set('database.connections.datadb.database', 'motogb_dms_mgb');

        //DB::reconnect('datadb');
        //Schema::connection('datadb')->getConnection()->reconnect();
        
    }

    /**
     * Get Human Readable Account Type.
     *
     * @param string $type
     *
     * @return string
     */
    public static function getType($type)
    {
        return config('ifrs')['accounts'][$type];
    }

    /**
     * Get Human Readable Account types
     *
     * @param array $types
     *
     * @return array
     */
    public static function getTypes($types)
    {
        $typeNames = [];

        foreach ($types as $type) {
            $typeNames[] = Account::getType($type);
        }
        return $typeNames;
    }

    /**
     * Get all accounts with opening balances for the given year
     *
     * @param int $year
     *
     * @return array
     */
    public static function openingBalances(int $year)
    {
        $accounts = collect([]);
        $balances = ['debit' => 0, 'credit' => 0];

        foreach (Account::all() as $account) {
            $account->openingBalance = $account->openingBalance($year);
            if ($account->openingBalance != 0) {
                $accounts->push($account);
            }
            if ($account->openingBalance > 0) {
                $balances['debit'] += $account->openingBalance;
            } else {
                $balances['credit'] += $account->openingBalance;
            }
        }
        return ['balances' => $balances, 'accounts' => $accounts];
    }

    /**
     * Chart of Account Section Balances for the Reporting Period.
     *
     * @param string $accountType
     * @param string | Carbon $startDate
     * @param string | Carbon $endDate
     *
     * @return array
     */
    public static function sectionBalances(
        array $accountTypes,
        $startDate = null,
        $endDate = null,
        $fullBalance = true
    ): array {
        $balances = ['sectionOpeningBalance' => 0, 'sectionClosingBalance' => 0, 'sectionMovement' => 0, 'sectionCategories' => []];

        //$startDate = is_null($startDate) ? ReportingPeriod::periodStart($endDate) : Carbon::parse($startDate);
        //$endDate = is_null($endDate) ? Carbon::now() : Carbon::parse($endDate);
        $startDate = is_null($startDate) ? ReportingPeriod::periodStart($endDate) : $startDate;
        $endDate = is_null($endDate) ? Carbon::now() : $endDate;
        
        $periodStart = ReportingPeriod::periodStart($endDate);

        $year = ReportingPeriod::year($endDate);

        foreach (Account::whereIn('account_type', $accountTypes)->get() as $account) {

            //$account->openingBalance = $account->openingBalance($year) + Ledger::balance($account, $periodStart, $startDate);
            $account->openingBalance = $account->openingBalance($year) + $account->currentBalance($periodStart, $startDate);
            $account->balanceMovement = $account->currentBalance($startDate, $endDate);
            
            //$account->closingBalance = $account->openingBalance + $account->balanceMovement;
            $account->closingBalance = $fullBalance ? $account->openingBalance + $account->balanceMovement : $account->balanceMovement;

            if(in_array("BANK",$accountTypes)){
                //echo 1;
            }
            
            $account->balanceMovement *= -1;

            if ($account->closingBalance <> 0 || $account->balanceMovement <> 0) {

                if (is_null($account->category)) {
                    $categoryName =  config('ifrs')['accounts'][$account->account_type];
                    $categoryId = 0;
                } else {
                    $category = $account->category;
                    $categoryName =  $category->name;
                    $categoryId = $category->id;
                }

                if (array_key_exists($categoryName, $balances['sectionCategories'])) {
                    $balances['sectionCategories'][$categoryName]['accounts']->push((object) $account->attributes);
                    $balances['sectionCategories'][$categoryName]['total'] += $account->closingBalance;
                } else {
                    $balances['sectionCategories'][$categoryName]['accounts'] = collect([(object) $account->attributes]);
                    $balances['sectionCategories'][$categoryName]['total'] = $account->closingBalance;
                    $balances['sectionCategories'][$categoryName]['id'] = $categoryId;
                }
                $balances['sectionOpeningBalance'] += $account->openingBalance;
                $balances['sectionMovement'] += $account->balanceMovement;
                $balances['sectionClosingBalance'] += $account->closingBalance;
            }
        }

        return $balances;
    }

    /**
     * Instance Type.
     *
     * @return string
     */
    public function getTypeAttribute()
    {
        return Account::getType($this->account_type);
    }

    /**
     * Instance Identifier.
     *
     * @return string
     */
    public function toString($type = false)
    {
        return $type ? $this->type . ': ' . $this->name : $this->name;
    }

    /**
     * Account Currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Account Category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Account Balances.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    /**
     * Account attributes.
     *
     * @return object
     */
    public function attributes()
    {
        $this->attributes['closingBalance'] = $this->closingBalance(date('Y-m-d'));
        return (object) $this->attributes;
    }

    /**
     * Get Account's Opening Balance for the Reporting Period.
     *
     * @param int $year
     *
     * @return float
     */
    public function openingBalance(int $year = null): float
    {
        if (!is_null($year)) {
            $period = ReportingPeriod::where('calendar_year', $year)->first();
            //$period = ReportingPeriod::getPeriod($year."-01-01");
        } else {
            $period = Auth::user()->entity->current_reporting_period;
        }
        
        $balance = 0;

        foreach ($this->balances->where('reporting_period_id', $period->id) as $record) {
            $amount = $record->amount / $record->exchangeRate->rate;
            $record->balance_type == Balance::DEBIT ? $balance += $amount : $balance -= $amount;
        }
        return $balance;
    }

    /**
     * Get Account's Current Balance for the Period given.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     *
     * @return float
     */
    //public function currentBalance(string $startDate = null, string $endDate = null): float
    public function currentBalance(Carbon $startDate = null, Carbon $endDate = null): float
    {

        //$startDate = is_null($startDate) ? ReportingPeriod::periodStart($endDate) : Carbon::parse($startDate);
        //$endDate = is_null($endDate) ? Carbon::now() : Carbon::parse($endDate);
        $startDate = is_null($startDate) ? ReportingPeriod::periodStart($endDate) : $startDate;
        $endDate = is_null($endDate) ? Carbon::now() : $endDate;
        return Ledger::balance($this, $startDate, $endDate);
    }

    /**
     * Get Account's Closing Balance for the Reporting Period.
     *
     * @param string $endDate
     *
     * @return float
     */
    public function closingBalance(string $endDate = null): float
    {
        //$endDate = is_null($endDate) ? Carbon::now() : $endDate;
        $endDate = is_null($endDate) ? ReportingPeriod::periodEnd() : Carbon::parse($endDate);
        $startDate = ReportingPeriod::periodStart($endDate);
        $year = ReportingPeriod::year($endDate);

        return $this->openingBalance($year) + $this->currentBalance($startDate, $endDate);
    }

    /**
     * Account Transactions Query
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     *
     * @return Builder
     */
    public function transactionsQuery(Carbon $startDate, Carbon $endDate)
    {
        $transactionTable = config('ifrs.table_prefix') . 'transactions';
        $ledgerTable = config('ifrs.table_prefix') . 'ledgers';

        $query = DB::table(
            $transactionTable
        )
            ->leftJoin($ledgerTable, $transactionTable . '.id', '=', $ledgerTable . '.transaction_id')
            ->where($transactionTable . '.deleted_at', null)
            ->where($transactionTable . '.entity_id', $this->entity_id)
            ->where($transactionTable . '.transaction_date', '>=', $startDate)
            //->where($transactionTable . '.transaction_date', '<=', $endDate)
            ->where($transactionTable . '.transaction_date', '<=', $endDate->endOfDay())
            ->where($transactionTable . '.currency_id', $this->currency_id)
            ->select(
                $transactionTable . '.id',
                $transactionTable . '.transaction_date',
                $transactionTable . '.transaction_no',
                $transactionTable . '.reference',
                $transactionTable . '.transaction_type',
                $transactionTable . '.narration',
                $transactionTable . '.document',
                $transactionTable . '.order_type',
                $transactionTable . '.order_no',
                $transactionTable . '.due_date',
                $transactionTable . '.customer_id',
            )->distinct();

        $query->where(
            function ($query) use ($ledgerTable) {
                $query->where($ledgerTable . '.post_account', $this->id)
                    ->orwhere($ledgerTable . '.folio_account', $this->id);
            }
        );

        return $query;
    }

    /**
     * Get Account's Transactions for the Reporting Period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     *
     * @return array
     */
    public function getTransactions(string $startDate = null, string $endDate = null): array
    {

        $transactions = ['total' => 0, 'transactions' => []];
        $startDate = is_null($startDate) ? ReportingPeriod::periodStart($endDate) : Carbon::parse($startDate);
        $endDate = is_null($endDate) ? Carbon::now() : Carbon::parse($endDate);

        foreach ($this->transactionsQuery($startDate, $endDate)->get() as $transaction) {

            $transaction->amount = abs(Ledger::contribution($this, $transaction->id));
            $transaction->type = Transaction::getType($transaction->transaction_type);
            $transaction->date = Carbon::parse($transaction->transaction_date)->toFormattedDateString();
            $transactions['transactions'][] = $transaction;
            $transactions['total'] += $transaction->amount;
        }
        return $transactions;
    }

    /**
     * Validate Account Code.
     */
    public function save(array $options = []): bool
    {
        if (!isset($this->currency_id) && Auth::user()->entity) {
            $this->currency_id = Auth::user()->entity->currency_id;
        }

        
        if (is_null($this->code) || $this->isDirty('account_type')) {

            if (is_null($this->account_type)) {
                throw new MissingAccountType();
            }

            if (is_null($this->code)){
                //$this->code = config('ifrs')['account_codes'][$this->account_type] + Account::withTrashed()
                //    ->where('account_type', $this->account_type)
                //    ->count() + 1;

                $this->code = $this->getAccountCode();
            }

        }

        if (!is_null($this->category) && $this->category->category_type != $this->account_type) {
            throw new InvalidCategoryType($this->account_type, $this->category->category_type);
        }

        $this->name = ucfirst($this->name);
        return parent::save($options);
    }

    /**
     * Calculate Account Code.
     */
    private function getAccountCode(): int
    {
        $query = Account::withTrashed()
        ->where('account_type', $this->account_type);

        if(!is_null($this->entity_id)){
            $query->withoutGlobalScopes()->where('entity_id', $this->entity_id);
        }
        return config('ifrs')['account_codes'][$this->account_type] + $query->count() + 1;
    }

    /**
     * Check for Current Year Transactions.
     */
    public function delete(): bool
    {
        if ($this->closingBalance() != 0) {
            throw new HangingTransactions();
        }

        return parent::delete();
    }

    public function getcodeNameAttribute(){
        return (trim($this->code).' - '.trim($this->name));
    }
}
