<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Reports;

use IFRS\Models\Account;
use IFRS\Models\ReportingPeriod;

class TrialBalance extends FinancialStatement
{
    /**
     * Trial Balance Title
     *
     * @var string
     */
    const TITLE = 'TRIAL_BALANCE';

    /**
     * Trial Balance Reporting Period.
     *
     * @var string
     */
    public $reportingPeriod = null;


    /**
     * Construct Trial Balance
     *
     * @param string $year
     */
    public function __construct(string $year = null)
    {
        parent::__construct($year);

        $this->reportingPeriod = is_null($year) ? (string) ReportingPeriod::year() : $year;
        $this->endDate = ReportingPeriod::periodEnd($year . "-01-01");

        $this->accounts[IncomeStatement::TITLE] = [];
        $this->accounts[BalanceSheet::TITLE] = [];

        $this->results[IncomeStatement::TITLE] = ['debit' => 0, 'credit' => 0];
        $this->results[BalanceSheet::TITLE] = ['debit' => 0, 'credit' => 0];
    }

    /**
     * Get Trial Balance Sections.
     */
    //public function getSections(): void
    public function getSections($startDate = null, $endDate = null, $fullbalance = true): void
    {
        foreach (Account::all() as $account) {
            $balance = $account->closingBalance($this->endDate);
            if ($balance <> 0) {
                if ($balance > 0) {
                    $this->balances["debit"] += abs($balance);
                } else {
                    $this->balances["credit"] += abs($balance);
                }

                $this->getIncomeStatementSections($account, $balance);
                $this->getBalanceSheetSections($account, $balance);
            }

        }
    }

    /**
     * Get Income Statement Sections.
     *
     * @param Account $account
     * @param float   $balance
     */
    private function getIncomeStatementSections(Account $account, $balance): void
    {

        if (in_array($account->account_type, IncomeStatement::getAccountTypes())) {

            if ($balance > 0) {
                $this->results[IncomeStatement::TITLE]["debit"] += abs($balance);
            } else {
                $this->results[IncomeStatement::TITLE]["credit"] += abs($balance);
            }

            if (array_key_exists($account->account_type, $this->accounts[IncomeStatement::TITLE])) {
                $this->accounts[IncomeStatement::TITLE][$account->account_type]['accounts']->push($account->attributes());
                $this->accounts[IncomeStatement::TITLE][$account->account_type]['balance'] += $balance;
            } else {
                $this->accounts[IncomeStatement::TITLE][$account->account_type]['accounts'] = collect([$account->attributes()]);
                $this->accounts[IncomeStatement::TITLE][$account->account_type]['balance'] = $balance;
            }
        }
    }

    /**
     * Get Balance Sheet Sections.
     *
     * @param Account $account
     * @param float   $balance
     */
    private function getBalanceSheetSections(Account $account, $balance): void
    {
        if (in_array($account->account_type, BalanceSheet::getAccountTypes())) {

            if ($balance > 0) {
                $this->results[BalanceSheet::TITLE]["debit"] += abs($balance);
            } else {
                $this->results[BalanceSheet::TITLE]["credit"] += abs($balance);
            }

            if (array_key_exists($account->account_type, $this->accounts[BalanceSheet::TITLE])) {
                $this->accounts[BalanceSheet::TITLE][$account->account_type]['accounts']->push($account->attributes());
                $this->accounts[BalanceSheet::TITLE][$account->account_type]['balance'] += $balance;
            } else {
                $this->accounts[BalanceSheet::TITLE][$account->account_type]['accounts'] = collect([$account->attributes()]);
                $this->accounts[BalanceSheet::TITLE][$account->account_type]['balance'] = $balance;
            }
        }
    }
}
