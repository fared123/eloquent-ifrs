<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Transactions;

use IFRS\Interfaces\Buys;
use IFRS\Interfaces\Fetchable;
use IFRS\Interfaces\Assignable;

use IFRS\Traits\Buying;
use IFRS\Traits\Fetching;
use IFRS\Traits\Assigning;

use IFRS\Models\Transaction;

class SupplierBillCredit extends Transaction implements Buys, Fetchable, Assignable
{
    use Buying;
    use Fetching;
    use Assigning;

    use \Parental\HasParent;

    /**
     * Transaction Number prefix
     *
     * @var string
     */

    const PREFIX = Transaction::BC;

    /**
     * Construct new CSupplierBillCredit
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $attributes['credited'] = true;
        $attributes['transaction_type'] = self::PREFIX;

        parent::__construct($attributes);
    }
}
