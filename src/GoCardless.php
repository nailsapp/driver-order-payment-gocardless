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

namespace Nails\Invoice\Driver\Payment;

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
     * Returns any data which should be POSTED to the endpoint as part of a redirect
     * flow; if empty a header redirect is used instead.
     * @return array
     */
    public function getRedirectPostData()
    {
        return array();
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the payment fields the driver requires, use self::PAYMENT_FIELDS_CARD
     * for basic credit card details.
     * @return mixed
     */
    public function getPaymentFields()
    {
        return array(
            array(
                'key'      => 'sort_code',
                'label'    => 'Sort Code',
                'type'     => 'text',
                'required' => true
            ),
            array(
                'key'      => 'account_number',
                'label'    => 'Account Number',
                'type'     => 'text',
                'required' => true
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Take a payment
     * @param  array   $aData      Any data to use for processing the transaction, e.g., card details
     * @param  integer $iAmount    The amount to charge
     * @param  string  $sCurrency  The currency to charge in
     * @param  string  $sReturnUrl The return URL (if redirecting)
     * @return \Nails\Invoice\Model\ChargeResponse
     */
    public function charge($aData, $iAmount, $sCurrency, $sReturnUrl)
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
