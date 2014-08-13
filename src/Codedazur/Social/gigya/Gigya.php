<?php namespace Codedazur\Social\Gigya;

include_once('GSSDK.php');

use \Config;
use \SigUtils;

/**
 * @author rick@codedazur.nl
 * @copyright 2014 Code d'Azur
 */
 
class Gigya
{

    const METHOD_NOTIFY_REGISTRATION = 'socialize.notifyRegistration';
    const METHOD_GET_SESSION_INFO = 'socialize.getSessionInfo';

    /**
     * Registers a temporary Gigya user (uid) to a non-temporary one
     * @param $uid
     * @return string
     * @throws GigyaException
     */
    public static function register($uid)
    {
        $campaignId = Config::get('social::gigya.campaign_id');
        if (empty($campaignId)) {
            throw new GigyaException('social::gigya.campaign_id is not defined in config', 1);
        }

        $prefix = $campaignId . '_';

        // \Debugger::log('Registring Gigya user ' . $uid);

        if (strpos($uid, $prefix) != 0 || strpos($uid, $prefix) === false) {
            $siteUid = $prefix . time() . '_' . $uid;

            // Make request to set the siteUID
            $response = self::request(self::METHOD_NOTIFY_REGISTRATION, array(
                'UID' => $uid,
                'siteUID' => $siteUid,
                'UIDTimestamp' => time(),
            ));

            // \Debugger::log('Trying site ID ' . $siteUid);

            // Handle response
            if ($response->getErrorCode() == 0) {
                // \Debugger::log('=> Registration successful');
                return $siteUid;
            } else {
                // \Debugger::log('=> Registration failed: ' . $response->getErrorMessage());
                throw new GigyaException($response->getErrorMessage(), $response->getErrorCode());
            }
        } else {
            // \Debugger::log('=> Already registered');

            // User ID seems already to be registered
            return $uid;
        }
    }

    /**
     * @param $uid
     * @param string $provider
     * @return \GSResponse
     * @throws GigyaException
     */
    public static function getSession($uid, $provider = 'facebook')
    {
        // Make request to set the siteUID
        $response = self::request(self::METHOD_GET_SESSION_INFO, array(
            'UID' => $uid,
            'provider' => $provider,
        ));

        // Handle response
        if ($response->getErrorCode() == 0) {
            return $response->getData();
        } else {
            throw new GigyaException($response->getErrorMessage(), $response->getErrorCode());
        }
    }

    /**
     * @param $method
     * @param array $params
     * @return \GSResponse
     * @throws GigyaException
     */
    protected static function request($method, $params = array())
    {
        $apiKey = Config::get('social::gigya.api_key');
        $secretKey = Config::get('social::gigya.secret_key');
        $apiDomain = Config::get('social::gigya.api_domain', 'eu1.gigya.com');

        if (empty($apiKey) || empty($secretKey)) {
            throw new GigyaException('social::gigya.campaign_id, social::gigya.api_key and/or social::gigya.secret_key not defined in config', 1);
        }

        // \Debugger::log('Using API domain ' . $apiDomain);

        $request = new \GSRequest($apiKey, $secretKey, $method, null, true);
        $request->setAPIDomain($apiDomain);

        foreach ($params as $key => $value) {
            $request->setParam($key, $value);
        }

        return $request->send();
    }

    /**
     * @param $uid
     * @param $signature
     * @param $timestamp
     * @return bool
     * @throws GigyaException
     */
    public static function validSignature($uid, $signature, $timestamp)
    {
        $secretKey = Config::get('social::gigya.secret_key');

        if (empty($uid) || empty($signature) || empty($timestamp)) {
            throw new GigyaException('One or more of the provided parameters are empty', 2);
        }

        if (empty($secretKey)) {
            throw new GigyaException('social::gigya.secret_key is not defined in config', 1);
        }

        return SigUtils::validateUserSignature($uid, $timestamp, $secretKey, $signature);
    }

}
