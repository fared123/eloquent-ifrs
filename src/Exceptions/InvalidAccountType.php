<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Exceptions;

use Carbon\Carbon;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use IFRS\Models\Account;

class InvalidAccountType extends IFRSException
{

    /**
     * Invalid Account Type Exception
     *
     * @param array|string  $accountTypes
     * @param string $message
     * @param int    $code
     */
    public function __construct($accountTypes, string $message = null, string $code = null)
    {
        if (is_array($accountTypes)) {
            $accountTypes = Account::getTypes($accountTypes);
            $error = "Schedule Account Type must be one of: " . implode(", ", $accountTypes);
        } else {
            $accountTypes = Account::getType($accountTypes);
            $error = "Vat Account must be of Type " . $accountTypes;
        }

        Log::notice(
            $error . ' ' . $message,
            [
                'user_id' => Auth::user()->id,
                'time' => Carbon::now(),
            ]
        );
        parent::__construct($error . ' ' . $message, $code);
    }
}
