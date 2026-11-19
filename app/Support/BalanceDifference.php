<?php

namespace App\Support;

use RuntimeException;

/**
 * Stops concluding a statement when the app's balance on its last day isn't the
 * bank's (the transaction rolls back); the page asks before concluding anyway.
 */
final class BalanceDifference extends RuntimeException
{
    public function __construct(public readonly float $difference)
    {
        parent::__construct('The balance differs from the bank.');
    }
}
