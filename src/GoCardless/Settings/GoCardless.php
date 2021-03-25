<?php

namespace Nails\Invoice\Driver\Payment\GoCardless\Settings;

use Nails\Common\Helper\Form;
use Nails\Common\Interfaces;
use Nails\Common\Service\FormValidation;
use Nails\Components\Setting;
use Nails\Currency;
use Nails\Factory;

/**
 * Class GoCardless
 *
 * @package Nails\Invoice\Driver\Payment\GoCardless\Settings
 */
class GoCardless implements Interfaces\Component\Settings
{
    const KEY_LABEL                = 'sLabel';
    const KEY_ACCESS_TOKEN_SANDBOX = 'sAccessTokenSandbox';
    const KEY_ACCESS_TOKEN_LIVE    = 'sAccessTokenLive';

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'GoCardless';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        /** @var Setting $oLabel */
        $oLabel = Factory::factory('ComponentSetting');
        $oLabel
            ->setKey(static::KEY_LABEL)
            ->setLabel('Label')
            ->setInfo('The name of the provider, as seen by customers.')
            ->setDefault('GoCardless')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oAccessTokenSandbox */
        $oAccessTokenSandbox = Factory::factory('ComponentSetting');
        $oAccessTokenSandbox
            ->setKey(static::KEY_ACCESS_TOKEN_SANDBOX)
            ->setType(Form::FIELD_PASSWORD)
            ->setLabel('Access Token')
            ->setEncrypted(true)
            ->setFieldset('API Keys - Test');

        /** @var Setting $oAccessTokenLive */
        $oAccessTokenLive = Factory::factory('ComponentSetting');
        $oAccessTokenLive
            ->setKey(static::KEY_ACCESS_TOKEN_LIVE)
            ->setType(Form::FIELD_PASSWORD)
            ->setLabel('Access Token')
            ->setEncrypted(true)
            ->setFieldset('API Keys - Live');

        return [
            $oLabel,
            $oAccessTokenSandbox,
            $oAccessTokenLive,
        ];
    }
}
