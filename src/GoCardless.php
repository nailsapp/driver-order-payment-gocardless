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

use DateTime;
use GoCardlessPro;
use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\ApiConnectionException;
use GoCardlessPro\Core\Exception\ApiException;
use GoCardlessPro\Core\Exception\MalformedResponseException;
use Nails\Auth\Service\Session;
use Nails\Auth\Service\User\Meta;
use Nails\Common\Exception\NailsException;
use Nails\Environment;
use Nails\Factory;
use Nails\Invoice\Driver\PaymentBase;
use Nails\Invoice\Exception\DriverException;
use Nails\Invoice\Factory\ChargeResponse;
use Nails\Invoice\Factory\CompleteResponse;
use Nails\Invoice\Factory\RefundResponse;
use Nails\Invoice\Factory\ScaResponse;
use stdClass;

/**
 * Class GoCardless
 *
 * @package Nails\Invoice\Driver\Payment
 */
class GoCardless extends PaymentBase
{
    protected $sMandateTable = NAILS_DB_PREFIX . 'user_meta_invoice_gocardless_mandate';
    protected $aMandates;

    // --------------------------------------------------------------------------

    const SESSION_TOKEN_KEY = 'gocardless_session_token';

    // --------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        //  Get any mandates this user might have
        if (isLoggedIn()) {

            /** @var Meta $oUserMeta */
            $oUserMeta       = Factory::service('UserMeta', 'nails/module-auth');
            $this->aMandates = $oUserMeta->getMany($this->sMandateTable, activeUser('id'));

        } else {

            $this->aMandates = [];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns whether the driver is available to be used against the selected invoice
     *
     * @param stdClass $oInvoice The invoice being charged
     *
     * @return bool
     */
    public function isAvailable($oInvoice): bool
    {
        // This driver can only be used with logged in users
        return isLoggedIn();
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the currencies which this driver supports, it will only be presented
     * when attempting to pay an invoice in a supported currency
     *
     * @return string[]|null
     */
    public function getSupportedCurrencies(): ?array
    {
        //  @todo (Pablo - 2019-08-01) - Automate this
        return ['AUD', 'CAD', 'DKK', 'EUR', 'GBP', 'NZD', 'SEK', 'USD'];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns whether the driver uses a redirect payment flow or not.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return empty($this->aMandates);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the payment fields the driver requires, 'CARD' for basic credit
     * card details.
     *
     * @return mixed
     */
    public function getPaymentFields()
    {
        if (!empty($this->aMandates) && count($this->aMandates) > 1) {

            $aOptions = [
                '' => 'Please choose',
            ];
            foreach ($this->aMandates as $oMandate) {
                $aOptions[$oMandate->id] = $oMandate->label;
            }

            return [
                [
                    'key'      => 'mandate_id',
                    'type'     => 'dropdown',
                    'label'    => 'Mandate',
                    'required' => true,
                    'options'  => $aOptions,
                ],
            ];

        } else {

            return [];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any assets to load during checkout
     *
     * @return array
     */
    public function getCheckoutAssets(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Initiate a payment
     *
     * @param int      $iAmount      The payment amount
     * @param string   $sCurrency    The payment currency
     * @param stdClass $oData        The driver data object
     * @param stdClass $oCustomData  The custom data object
     * @param string   $sDescription The charge description
     * @param stdClass $oPayment     The payment object
     * @param stdClass $oInvoice     The invoice object
     * @param string   $sSuccessUrl  The URL to go to after successful payment
     * @param string   $sErrorUrl    The URL to go to after failed payment
     *
     * @return ChargeResponse
     */
    public function charge(
        $iAmount,
        $sCurrency,
        $oData,
        $oCustomData,
        $sDescription,
        $oPayment,
        $oInvoice,
        $sSuccessUrl,
        $sErrorUrl
    ): ChargeResponse {

        /** @var ChargeResponse $oChargeResponse */
        $oChargeResponse = Factory::factory('ChargeResponse', 'nails/module-invoice');

        try {

            $oClient = $this->getClient();

            /**
             * What we do here depends on a number of things:
             * - If a mandate_id is specified then use it
             *   - If it is passed in as $oCustomData data then use it. It's coming from the developer.
             *   - If it is passed in as $oData then it's coming from the user and needs to be checked
             * - If we have 1 mandate then we're using it
             * - If we have 0 mandates, then we're using a redirect flow
             */
            $sMandateId = null;
            $bRedirect  = false;

            if (!empty($oCustomData->mandate_id)) {

                //  Supplied by dev, trust it
                $sMandateId = $oCustomData->mandate_id;

            } elseif (!empty($oData->mandate_id)) {

                //  Supplied by user, validate they have the right to use it
                foreach ($this->aMandates as $oMandate) {
                    if ($oMandate->id == $oData->mandate_id) {
                        $sMandateId = $oMandate->mandate_id;
                        break;
                    }
                }

            } elseif (count($this->aMandates) == 1) {

                //  Nothing supplied, but 1 mandate detected, use it
                $sMandateId = $this->aMandates[0]->mandate_id;

            } else {

                //  Redirect flow
                $bRedirect = true;
            }

            if ($bRedirect) {

                /**
                 * Generate a random session token
                 * GoCardless uses this to verify that the person completing the redirect flow
                 * is the same person who initiated it.
                 */

                Factory::helper('string');
                /** @var Session $oSession */
                $oSession      = Factory::service('Session', 'nails/module-auth');
                $sSessionToken = random_string('alnum', 32);
                $oSession->setUserData(self::SESSION_TOKEN_KEY, $sSessionToken);

                //  Create a new redirect flow
                $oGCResponse = $oClient->redirectFlows()->create(
                    [
                        'params' => [
                            'session_token'        => $sSessionToken,
                            'success_redirect_url' => $sSuccessUrl,
                        ],
                    ]
                );

                if ($oGCResponse->api_response->status_code === 201) {

                    $oChargeResponse->setRedirectUrl(
                        $oGCResponse->api_response->body->redirect_flows->redirect_url
                    );

                } else {

                    //  @todo: handle errors returned by the GoCardless Client/API
                    $oChargeResponse->setStatusFailed(
                        null,
                        0,
                        'The gateway rejected the request, you may wish to try again.'
                    );
                }

            } elseif (empty($sMandateId)) {
                throw new DriverException('Missing Mandate ID.', 1);
            } else {
                //  Create a payment against the mandate
                $sTxnId = $this->createPayment(
                    $oClient,
                    $sMandateId,
                    $sDescription,
                    $iAmount,
                    $sCurrency,
                    $oInvoice,
                    $oCustomData
                );

                if (!empty($sTxnId)) {

                    //  Set the response as processing, GoCardless will let us know when the payment is complete
                    $oChargeResponse->setStatusProcessing();
                    $oChargeResponse->setTxnId($sTxnId);
                    $oChargeResponse->setFee($this->calculateFee($iAmount));

                } else {

                    $oChargeResponse->setStatusFailed(
                        null,
                        0,
                        'The gateway rejected the request, you may wish to try again.'
                    );
                }
            }

        } catch (ApiConnectionException $e) {

            //  Network error
            $oChargeResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'There was a problem connecting to the gateway, you may wish to try again.'
            );

        } catch (ApiException $e) {

            //  API request failed / record couldn't be created.
            $oChargeResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway rejected the request, you may wish to try again.'
            );

        } catch (MalformedResponseException $e) {

            //  Unexpected non-JSON response
            $oChargeResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway returned a malformed response, you may wish to try again.'
            );

        } catch (\Exception $e) {
            $oChargeResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'An error occurred while executing the request.'
            );
        }

        return $oChargeResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Handles any SCA requests
     *
     * @param ScaResponse $oScaResponse The SCA Response object
     * @param array       $aData        Any saved SCA data
     * @param string      $sSuccessUrl  The URL to redirect to after authorisation
     *
     * @return ScaResponse
     */
    public function sca(ScaResponse $oScaResponse, array $aData, string $sSuccessUrl): ScaResponse
    {
        //  @todo (Pablo - 2019-07-24) - Implement this method
    }

    // --------------------------------------------------------------------------

    /**
     * Complete the payment
     *
     * @param stdClass $oPayment  The Payment object
     * @param stdClass $oInvoice  The Invoice object
     * @param array    $aGetVars  Any $_GET variables passed from the redirect flow
     * @param array    $aPostVars Any $_POST variables passed from the redirect flow
     *
     * @return CompleteResponse
     */
    public function complete($oPayment, $oInvoice, $aGetVars, $aPostVars): CompleteResponse
    {
        /** @var CompleteResponse $oCompleteResponse */
        $oCompleteResponse = Factory::factory('CompleteResponse', 'nails/module-invoice');

        try {

            $oClient = $this->getClient();

            //  Retrieve data required for the completion
            $sRedirectFlowId = getFromArray('redirect_flow_id', $aGetVars);
            /** @var Session $oSession */
            $oSession      = Factory::service('Session', 'nails/module-auth');
            $sSessionToken = $oSession->getUserData(self::SESSION_TOKEN_KEY);

            $oSession->unsetUserData(self::SESSION_TOKEN_KEY);

            if (empty($sRedirectFlowId)) {

                $oCompleteResponse->setStatusFailed(
                    'The complete request was missing $_GET[\'redirect_flow_id\']',
                    0,
                    'The request failed to complete, data was missing.'
                );

            } elseif (empty($sSessionToken)) {

                $oCompleteResponse->setStatusFailed(
                    'The complete request was missing the session token',
                    0,
                    'The request failed to complete, data was missing.'
                );

            } else {

                //  Complete the redirect flow
                $oGCResponse = $oClient->redirectFlows()->complete(
                    $sRedirectFlowId,
                    [
                        'params' => [
                            'session_token' => $sSessionToken,
                        ],
                    ]
                );

                if ($oGCResponse->api_response->status_code === 200) {

                    //  Save the mandate against user meta
                    /** @var Meta $oUserMeta */
                    $oUserMeta = Factory::service('UserMeta', 'nails/module-auth');
                    /** @var DateTime $oNow */
                    $oNow       = Factory::factory('DateTime');
                    $sMandateId = $oGCResponse->api_response->body->redirect_flows->links->mandate;

                    $oUserMeta->update(
                        $this->sMandateTable,
                        activeUser('id'),
                        [
                            'label'      => 'Direct Debit Mandate (Created ' . $oNow->format('jS F, Y') . ')',
                            'mandate_id' => $sMandateId,
                            'created'    => $oNow->format('Y-m-d H:i:s'),
                        ]
                    );

                    //  Create a payment against the mandate
                    $sTxnId = $this->createPayment(
                        $oClient,
                        $sMandateId,
                        $oPayment->description,
                        $oPayment->amount->raw,
                        $oPayment->currency->code,
                        $oInvoice,
                        $oPayment->custom_data
                    );

                    if (!empty($sTxnId)) {

                        //  Set the response as processing, GoCardless will let us know when the payment is complete
                        $oCompleteResponse->setStatusProcessing();
                        $oCompleteResponse->setTxnId($sTxnId);
                        $oCompleteResponse->setFee($this->calculateFee($oPayment->amount->raw));

                    } else {

                        $oCompleteResponse->setStatusFailed(
                            null,
                            0,
                            'The gateway rejected the request, you may wish to try again.'
                        );
                    }

                } else {

                    //  @todo: handle errors returned by the GoCardless Client/API
                    $oCompleteResponse->setStatusFailed(
                        null,
                        0,
                        'The gateway rejected the request, you may wish to try again.'
                    );
                }
            }

        } catch (ApiConnectionException $e) {

            //  Network error
            $oCompleteResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'There was a problem connecting to the gateway, you may wish to try again.'
            );

        } catch (ApiException $e) {

            //  API request failed / record couldn't be created.
            $oCompleteResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway rejected the request, you may wish to try again.'
            );

        } catch (MalformedResponseException $e) {

            //  Unexpected non-JSON response
            $oCompleteResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway returned a malformed response, you may wish to try again.'
            );

        } catch (\Exception $e) {
            $oCompleteResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'An error occurred while executing the request.'
            );
        }

        return $oCompleteResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a payment against a mandate
     *
     * @param Client   $oClient      The GoCardless client
     * @param string   $sMandateId   The mandate ID
     * @param string   $sDescription The payment\'s description
     * @param int      $iAmount      The amount of the payment
     * @param string   $sCurrency    The currency in which to take payment
     * @param stdClass $oInvoice     The invoice object
     * @param stdClass $oCustomData  The payment'scustom data object
     *
     * @return string
     */
    protected function createPayment(
        $oClient,
        $sMandateId,
        $sDescription,
        $iAmount,
        $sCurrency,
        $oInvoice,
        $oCustomData
    ): string {

        $aMetaData   = $this->extractMetaData($oInvoice, $oCustomData);
        $oGCResponse = $oClient->payments()->create(
            [
                'params' => [
                    'description' => $sDescription,
                    'amount'      => $iAmount,
                    'currency'    => $sCurrency,
                    'metadata'    => $aMetaData,
                    'links'       => [
                        'mandate' => $sMandateId,
                    ],
                ],
            ]
        );

        $sTxnId = null;

        if ($oGCResponse->api_response->status_code === 201) {
            $sTxnId = $oGCResponse->api_response->body->payments->id;
        }

        return $sTxnId;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the GoCardless Client
     *
     * @return Client
     * @throws DriverException
     */
    protected function getClient(): Client
    {
        if (Environment::is(Environment::ENV_PROD)) {

            $sAccessToken = $this->getSetting('sAccessTokenLive');
            $sEnvironment = GoCardlessPro\Environment::LIVE;

        } else {

            $sAccessToken = $this->getSetting('sAccessTokenSandbox');
            $sEnvironment = GoCardlessPro\Environment::SANDBOX;
        }

        if (empty($sAccessToken)) {
            throw new DriverException('Missing GoCardless Access Token.', 1);
        }

        $oClient = new Client([
            'access_token' => $sAccessToken,
            'environment'  => $sEnvironment,
        ]);

        return $oClient;
    }

    // --------------------------------------------------------------------------

    /**
     * Extract the meta data from the invoice and custom data objects
     *
     * @param stdClass $oInvoice    The invoice object
     * @param stdClass $oCustomData The custom data object
     *
     * @return array
     */
    protected function extractMetaData($oInvoice, $oCustomData): array
    {
        //  Store any custom meta data; GC allows up to 3 key value pairs with key
        //  names up to 50 characters and values up to 500 characters.

        //  In practice only one custom key can be defined
        $aMetaData = [
            'invoiceId'  => $oInvoice->id,
            'invoiceRef' => $oInvoice->ref,
        ];

        if (!empty($oCustomData->metadata)) {
            $aMetaData = array_merge($aMetaData, (array) $oCustomData->metadata);
        }

        $aCleanMetaData = [];
        $iCounter       = 0;

        foreach ($aMetaData as $sKey => $mValue) {

            if ($iCounter === 3) {
                break;
            }

            $aCleanMetaData[substr($sKey, 0, 50)] = substr((string) $mValue, 0, 500);
            $iCounter++;
        }

        return $aCleanMetaData;
    }

    // --------------------------------------------------------------------------

    /**
     * Calculate the fee which will be charged by GoCardless
     *
     * @param int $iAmount The amount of the transaction
     *
     * @return int
     */
    protected function calculateFee($iAmount): int
    {
        /**
         * As of 17/03/2015 there is no API method or property describing the fee which GoCardless will charge
         * However, their charging mechanic is simple: 1% of the total transaction (rounded up to nearest penny)
         * and capped at £2.
         *
         * Until such an API method exists, we'll calculate it ourselves - it should be accurate. Famous last words...
         */

        $iFee = intval(ceil($iAmount * 0.01));
        $iFee = $iFee > 200 ? 200 : $iFee;
        return $iFee;
    }

    // --------------------------------------------------------------------------

    /**
     * Issue a refund for a payment
     *
     * @param string   $sTxnId      The transaction's ID
     * @param int      $iAmount     The amount to refund
     * @param string   $sCurrency   The currency in which to refund
     * @param stdClass $oCustomData The custom data object
     * @param string   $sReason     The refund's reason
     * @param stdClass $oPayment    The payment object
     * @param stdClass $oInvoice    The invoice object
     *
     * @return RefundResponse
     */
    public function refund(
        $sTxnId,
        $iAmount,
        $sCurrency,
        $oCustomData,
        $sReason,
        $oPayment,
        $oInvoice
    ): RefundResponse {

        /** @var RefundResponse $oRefundResponse */
        $oRefundResponse = Factory::factory('RefundResponse', 'nails/module-invoice');

        //  Bail out on GoCardless refunds until we have an actual need (and can test it properly)
        $oRefundResponse->setStatusFailed(
            'GoCardless refunds are not available right now.',
            null,
            'GoCardless refunds are not available right now.'
        );
        return $oRefundResponse;

        //  In order to refund we need to know the value of all successful refunds to date
        //  plus we can only send up to 5 refunds total against a transaction
        //  @todo

        try {

            $oClient     = $this->getClient();
            $aMetaData   = $this->extractMetaData($oInvoice, $oCustomData);
            $oGCResponse = $oClient->refunds()->create(
                [
                    'params' => [
                        'amount'                    => $iAmount,
                        'metadata'                  => $aMetaData,
                        'total_amount_confirmation' => $iAmount,
                        'links'                     => [
                            'payment' => $sTxnId,
                        ],
                    ],
                ]
            );

            dumpanddie($oGCResponse);

            $sTxnId = null;

            //  @todo - correct?
            if ($oGCResponse->api_response->status_code === 201) {
                $sTxnId = $oGCResponse->api_response->body->refunds->id;
            }

            $oRefundResponse->setStatusProcessing();
            $oRefundResponse->setTxnId($sTxnId);
            //  @todo will this calculation be correct for partial payments?
            $oRefundResponse->setFee($this->calculateFee($iAmount));

        } catch (ApiConnectionException $e) {

            //  Network error
            $oRefundResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'There was a problem connecting to the gateway, you may wish to try again.'
            );

        } catch (ApiException $e) {

            //  API request failed / record couldn't be created.
            $oRefundResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway rejected the request, you may wish to try again.'
            );

        } catch (MalformedResponseException $e) {

            //  Unexpected non-JSON response
            $oRefundResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'The gateway returned a malformed response, you may wish to try again.'
            );

        } catch (\Exception $e) {

            $oRefundResponse->setStatusFailed(
                $e->getMessage(),
                $e->getCode(),
                'An error occurred while executing the request.'
            );
        }

        return $oRefundResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new payment source, returns a semi-populated source resource
     *
     * @param \Nails\Invoice\Resource\Source $oResource The Resouce object to update
     * @param array                          $aData     Data passed from the caller
     *
     * @throws DriverException
     */
    public function createSource(
        \Nails\Invoice\Resource\Source &$oResource,
        array $aData
    ): void {
        //  @todo (Pablo - 2019-09-05) - implement this
        throw new NailsException('Method not implemented');
    }
}
