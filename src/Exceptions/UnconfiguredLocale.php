<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2021, Germany
 * @license   MIT
 */

namespace IFRS\Exceptions;

use Carbon\Carbon;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UnconfiguredLocale extends IFRSException
{
    /**
     * Missing Account Exception
     *
     * @param $locale
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $locale, string $message = null, int $code = null)
    {
        $error = "Locale ".$locale." is not configured";

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