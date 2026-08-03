<?php

namespace App\Notifications;

use RuntimeException;

class EmailVerificationDeliveryException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The verification email could not be delivered.');
    }
}
