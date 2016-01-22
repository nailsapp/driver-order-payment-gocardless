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
use Nails\Invoice\Exception\DriverException;

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
        return array();
    }

    // --------------------------------------------------------------------------

    /**
     * Initiate a payment
     * @param  integer   $iAmount      The payment amount
     * @param  string    $sCurrency    The payment currency
     * @param  array     $aData        An array of driver data
     * @param  string    $sDescription The charge description
     * @param  \stdClass $oPayment     The payment object
     * @param  \stdClass $oInvoice     The invoice object
     * @param  string    $sSuccessUrl  The URL to go to after successfull payment
     * @param  string    $sFailUrl     The URL to go to after failed payment
     * @return \Nails\Invoice\Model\ChargeResponse
     */
    public function charge(
        $iAmount,
        $sCurrency,
        $aData,
        $sDescription,
        $oPayment,
        $oInvoice,
        $sSuccessUrl,
        $sFailUrl
    )
    {
        $oChargeResponse = Factory::factory('ChargeResponse', 'nailsapp/module-invoice');

        try {

            if (ENVIRONMENT === 'PRODUCTION') {

                $sAccessToken = $this->getSetting('sAccessTokenLive');
                $sEnvironment = \GoCardlessPro\Environment::LIVE;

            } else {

                $sAccessToken = $this->getSetting('sAccessTokenSandbox');
                $sEnvironment = \GoCardlessPro\Environment::SANDBOX;
            }

            if (empty($sAccessToken)) {
                throw new DriverException('Missing GoCardless Access Token.', 1);
            }

            $oClient = new \GoCardlessPro\Client(
                array(
                    'access_token' => $sAccessToken,
                    'environment'  => $sEnvironment
                )
            );

            //  Create a new redirect flow
            $oGCResponse = $oClient->redirectFlows()->create(
                array(
                    'params' => array(
                        'description'          => $sDescription,
                        'session_token'        => 'xxx',
                        'success_redirect_url' => $sSuccessUrl
                    )
                )
            );

            if ($oGCResponse->api_response->status_code === 201) {

                $oChargeResponse->setRedirectUrl(
                    $oGCResponse->api_response->body->redirect_flows->redirect_url
                );

            } else {

                //  @todo: handle errors returned by the GoCardless Client/API
                $oChargeResponse->setStatusFail(
                    null,
                    0,
                    'The gateway rejected the request, you may wish to try again.'
                );
            }

        } catch (\GoCardlessPro\Core\Exception\ApiConnectionException $e) {

            //  Network error
            $oChargeResponse->setStatusFail(
                $e->getMessage(),
                $e->getCode(),
                'There was a problem connecting to the gateway, you may wish to try again.'
            );

        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {

            //  API request failed / record couldn't be created.
            $oChargeResponse->setStatusFail(
                $e->getMessage(),
                $e->getCode(),
                'The gateway rejected the request, you may wish to try again.'
            );

        } catch (\GoCardlessPro\Core\Exception\MalformedResponseException $e) {

            //  Unexpected non-JSON response
            $oChargeResponse->setStatusFail(
                $e->getMessage(),
                $e->getCode(),
                'The gateway returned a malformed response, you may wish to try again.'
            );

        } catch (\Exception $e) {

            $oChargeResponse->setStatusFail(
                $e->getMessage(),
                $e->getCode(),
                'An error occurred while executing the request.'
            );
        }

        return $oChargeResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Complete the payment
     * @param  array $aGetVars  Any $_GET variables passed from the redirect flow
     * @param  array $aPostVars Any $_POST variables passed from the redirect flow
     * @return \Nails\Invoice\Model\CompleteResponse
     */
    public function complete($aGetVars, $aPostVars)
    {
        $oCompleteResponse = Factory::factory('CompleteResponse', 'nailsapp/module-invoice');
        $oCompleteResponse->setStatusOk();
        $oCompleteResponse->setTxnId('abc123');
        return $oCompleteResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Issue a refund for a payment
     * @return \Nails\Invoice\Model\RefundResponse
     */
    public function refund()
    {
        dumpanddie('Refund');
        $oChargeResponse = Factory::factory('RefundResponse', 'nailsapp/module-invoice');
        return $oChargeResponse;
    }
}
