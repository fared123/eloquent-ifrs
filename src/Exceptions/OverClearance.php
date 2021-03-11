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

use IFRS\Models\Transaction;

class OverClearance extends IFRSException
{
    /**
     * Over Clearance Exception
     *
     * @param string $assignedType
     * @param float  $amount
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $assignedType, float $amount, string $message = null, string $code = null)
    {
        $assignedType = Transaction::getType($assignedType);

        $error = $assignedType . " Transaction amount remaining to be cleared is less than " . $amount;

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
