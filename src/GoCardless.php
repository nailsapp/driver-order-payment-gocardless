<?php

/**
 * GoCardless payment Driver
 *
 * @package     Nails
 * @subpackage  driver-order-payment-gocardless
 * @category    Driver
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\OrderPayment\Driver;

use Nails\OrderPayment\Driver\Base;

class GoCardless extends Base
{
    protected $sLabel = 'GoCardless';

    // --------------------------------------------------------------------------

    /**
     * Returns the driver's configurable options
     * @return array
     */
    public function getConfig(){
        return array();
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the driver using the saved values from getConfig();
     */
    public function setConfig($aConfig){
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Take a payment
     * @return boolean
     */
    public function charge(){
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Issue a refund for a payment
     * @return boolean
     */
    public function refund(){
        return true;
    }
}
