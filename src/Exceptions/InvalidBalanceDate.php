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

class InvalidBalanceDate extends IFRSException
{

    /**
     * Invalid Balance Date Exception
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $message = null, string $code = null)
    {
        $error = "Transaction date must be earlier than the first day of the Balance's Reporting Period ";

        Log::notice(
            $error . $message,
            [
                'user_id' => Auth::user()->id,
                'time' => Carbon::now(),
            ]
        );

        parent::__construct($error . ' ' . $message, $code);
    }
}
