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
     * Returns whether the driver uses a redirect payment flow or not.
     * @return boolean
     */
    public function isRedirect()
    {
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the payment fields the driver requires, use self::PAYMENT_FIELDS_CARD
     * for basic credit card details.
     * @return mixed
     */
    public function paymentFields()
    {
        return array(
            array(
                'key'   => '',
                'label' => '',
                'type'  => ''
            )
        );
    }

    // --------------------------------------------------------------------------

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
