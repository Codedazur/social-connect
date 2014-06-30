<?php namespace Codedazur\Social;

use Codedazur\Social\Facebook\ApiException;
use Codedazur\Social\Facebook\Core;

use \Session;
use \Request;
use \Config;
use \View;
use \Redirect;
use \Response;
use \Controller;

class FacebookController extends Controller
{
    /**
     * @var \Facebook\Core
     */
    public $api;
    /**
     * @var string
     */
    protected $_redirectUri;
    /**
     * @var ApiException
     */
    protected $_exception;

    public function __construct()
    {

        $this->_redirectUri = Request::root() . '/facebook/social-authentication-callback/?sid=' . Session::getId();
        $appId = Config::get('social::facebook.appID');
        $secret = Config::get('social::facebook.secret');
        
        if (!isset($appId, $secret)) {
            throw new ApiException(array('error' => array('message' => 'Facebook social connect Config variables not properly set')));
        }

        try {
            $this->api = new Core(array('appId' => $appId, 'secret' => $secret, 'cookie' => true));
        } catch (ApiException $e) {
            $this->_exception = $e;
            $this->_handleException($e);

        }
    }

    /**
     * Are we connected?
     * @return boolean
     */
    protected function _isConnected()
    {
        return $this->api->getUser() == 0 ? false : true;
    }

    /**
     *
     */
    protected function _handleException($e)
    {
        //$this->api->destroySession();
    }

    /**
     * Redirects to the Core login page
     */
    public function socialUserAuthenticationAction()
    {
        $fbScope = Config::get('social::facebook.scope');
        $scope = '';
        
        if (isset($_REQUEST['scope'])) {
            $scope = $_REQUEST['scope'];
        } else if (isset($fbScope)) {
            $scope = $fbScope;
        }

        $apiRedirectUri = $this->_redirectUri;

        // If type is redirect, create the social redirect url, used in the callback action
        if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'redirect' && isset($_SERVER['HTTP_REFERER'])) {
//            return Redirect::to($_SERVER['HTTP_REFERER']);
            // Sanitize the url, by unsetting some query elements

            $urlParts = parse_url($_SERVER['HTTP_REFERER']);
            $query = '';
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryStringParts);
                unset($queryStringParts['__st__'], $queryStringParts['__sr__']);
                $query = http_build_query($queryStringParts);
            }
            $socialRedirect = $urlParts['path'];
            $redirectData = urldecode($_REQUEST['__rdd__']);
            $apiRedirectUri .= (preg_match('/\?/', $apiRedirectUri) ? '&' : '?') . '__sr__=' . urlencode($socialRedirect) . '&__rdd__=' . urlencode($redirectData);
        }
        $url = $this->api->getLoginUrl(array('scope' => $scope, 'redirect_uri' => $apiRedirectUri));
        return Redirect::to($url);
    }

    /**
     * The Core login redirects to this window, initializes the Core session automatically with the provided token code
     * @return void
     */
    public function socialAuthenticationCallbackAction()
    {
        $this->layout = null;

        $success = true;
        $msg = null;
        $accessToken = null;
        $userID = null;

        // Was there an exception initializing the app?
        if (!is_null($this->_exception)) {
            $msg = $this->_exception->getMessage();
            $success = false;
        } // Was there an error in the logging in, or providing permissions part?
        else if (isset($_REQUEST['error'], $_REQUEST['error_reason'], $_REQUEST['error_description'])) {
            $this->api->destroySession();
            $msg = $_REQUEST['error_description'];
            $success = false;
        }


        // Fetch the token & user uid
        if ($success) {
            try {
                $accessToken = $this->api->getAccessToken();
                $userID = $this->api->getUser();
            } catch (ApiException $e) {
                $msg = $e->getMessage();

                $success = false;
            }
        }

        // Redirect?
        if (isset($_REQUEST['__sr__'])) {
            $redirectUri = urldecode($_REQUEST['__sr__']);
            $redirectData = urldecode($_REQUEST['__rdd__']);
            
            $data = array();

            $arrayUrl = explode('/',$redirectUri);
            $notifyData = array('referer' => $redirectData);
            $data = array_merge($data, $notifyData);

            $result = new \stdClass();
            $result->type = 'facebook.connect.' . ($success ? 'success' : 'failure');
            $result->msg = json_encode($data);

            $redirectUri .= (preg_match('/\?/', $redirectUri) ? '&' : '?') . '__st__=' . urlencode(json_encode($result));
            return Redirect::to($redirectUri);
        }

        View::share('type', 'facebook');
        View::share('msg', $msg);
        View::share('access', $accessToken);
        View::share('userID', $userID);
        View::share('success', $success);
        return Response::view('social-callback');

    }
}