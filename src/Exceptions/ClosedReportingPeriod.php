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

class ClosedReportingPeriod extends IFRSException
{
    /**
     * Closed Reporting Period Exception
     *
     * @param int $year
     * @param string $message
     * @param int    $code
     */
    public function __construct(int $year, string $message = null, string $code = null)
    {
        $error = "Transaction cannot be saved because the Reporting Period for " . $year . " is closed ";

        Log::notice(
            $error . $message,
            [
                'user_id' => Auth::user()->id,
                'time' => Carbon::now(),
            ]
        );
        parent::__construct($error . $message, $code);
    }
}
