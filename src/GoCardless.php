<?php

/**
 * GoCardless payment Driver
 *
 * @package     Nails
 * @subpackage  driver-invoice-gocardless
 * @category    Driver
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Invoice\Driver;

use Nails\Factory;
use Nails\Invoice\Driver\PaymentBase;

class GoCardless extends PaymentBase
{
    /**
     * Take a payment
     * @return \Nails\Invoice\Model\ChargeResponse
     */
    public function charge($aCard, $iAmount, $sCurrency)
    {
        $oResponse = Factory::factory('ChargeResponse', 'nailsapp/module-invoice');
        return $oResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Issue a refund for a payment
     * @return \Nails\Invoice\Model\RefundResponse
     */
    public function refund()
    {
        $oResponse = Factory::factory('RefundResponse', 'nailsapp/module-invoice');
        return $oResponse;
    }
}
