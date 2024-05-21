<?php

namespace VoucherlyApi;

defined( 'ABSPATH' ) || exit;

/**
 * Exception is the base class for all user exceptions.
 */
class NotSuccessException extends \Exception
{
	function __construct(int $code) { 
        
       parent::__construct("HTTP status is not 2xx", $code);
    }

}