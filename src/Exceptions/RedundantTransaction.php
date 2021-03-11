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

class RedundantTransaction extends IFRSException
{
    /**
     * Redundant Transaction Exception
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $message = null, string $code = null)
    {
        $error = "A Transaction Main Account cannot be one of the Line Item Accounts ";

        Log::notice(
            $error . $message,
            [
                'user_id' => Auth::user()->id,
                'time' => Carbon::now(),
            ]
        );

        parent::__construct($error . $message, $code = null);
    }
}
