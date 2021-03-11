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

class MissingCurrency extends IFRSException
{
    /**
     * Missing Currency Exception
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $message = null, string $code = null)
    {
        $error = "An Entity must have a Reporting Currency ";

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
