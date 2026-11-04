<?php

namespace App\Exceptions;

use RuntimeException;

/** The payment gateway could not be reached or answered with something unusable. The message never contains credentials. */
class PaymentGatewayException extends RuntimeException
{
}
