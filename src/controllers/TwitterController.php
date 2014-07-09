<?php namespace Codedazur\Social;

use Codedazur\Social\Twitter\OAuth;
use Codedazur\Social\Twitter\Core;

use \Config;
use \Session;
use \Request;
use \Response;
use \Redirect;
use \View;
use \Controller;

/**
 * Controller to connect our framework to Twitter
 */
class TwitterController extends Controller 
{
    /**
     * @var OAuth
     */
    public $api = null;
    /**
     * @var string
     */
    protected $_redirectUri;

    public function __construct()
    {

        Session::setName(Request::root() . '/twitter/');
        $this->_redirectUri = Request::root() . '/twitter/social-authentication-callback/?sid=' . Session::getId();

        $consumerKey = Config::get('social::twitter.key');
        $consumerSecret = Config::get('social::twitter.secret');
        
        if (!isset($consumerKey, $consumerSecret)) {
            throw new \Exception('Twitter social connect config variables not properly set');
        }

        $this->api = new OAuth(
            $consumerKey,
            $consumerSecret
        );
        
    }

    /**
     * Creates the request for twitter, signed with our app
     */
    public function socialUserAuthenticationAction()
    {
        $apiRedirectUri = $this->_redirectUri;

        $urlParts = parse_url($_SERVER['HTTP_REFERER']);
        $query = '';
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryStringParts);
            unset($queryStringParts['__st__'], $queryStringParts['__sr__']);
            $query = '?' . http_build_query($queryStringParts);
        }
        
        $redirectData = urldecode($_REQUEST['__rdd__']);
        
        $socialRedirect = '/';
        $apiRedirectUri .= (preg_match('/\?/', $apiRedirectUri) ? '&' : '?') . '__sr__=' . urlencode($socialRedirect) . '&__rdd__=' . urlencode($redirectData);
//        }
        // Request token and URL
        $requestCredentials = $this->api->getRequestToken($apiRedirectUri);
        $redirectUrl = $this->api->getAuthorizeUrl($requestCredentials);
        Session::set('requestToken', $requestCredentials);
        return Redirect::to($redirectUrl);
    }

    /**
     * Callback after the user (hopefully) has given us permission, if they haven't I'll kick 'em in the balls (Jimmy Rambo)
     */
    public function socialAuthenticationCallbackAction()
    {
        $this->layout = null;

        $msg = null;
        $success = false;

        if (!is_null(Request::get('oauth_verifier'))) {
            $consumerKey = Config::get('social::twitter.key');
            $consumerSecret = Config::get('social::twitter.secret');
            $requestToken = Session::get('requestToken');

            $this->api = new Core(
                $consumerKey,
                $consumerSecret,
                $requestToken['oauth_token'],
                $requestToken['oauth_token_secret']
            );

            $accessToken = $this->api->getAccessToken(Request::get('oauth_verifier'));
            $response = $this->api->get('account/verify_credentials');
            
            if(!empty($response)){
                $profileImage = str_replace('_normal', '',$response->profile_image_url_https);
                $id = $response->id;
                $name = $response->name;
            } else {
                $profileImage = '';
                $id = 0;
                $name = '';
            }
            
            if ($this->api->http_code == 200) {
                $success = true;
                Session::set('accessToken', $accessToken);
            } else {
                $msg = 'Wrong HTTP response code';
                $success = false;
            }
        } else {
            $msg = 'Missing oAuth verifier';
        }

        $sessionTwitter = Session::get('accessToken');
        $firstName = explode(' ',trim($name))[0];
        $data = array('profile_picture' => $profileImage, 'first_name' => $firstName, 'id' => $id);

        // Redirect?
        if (isset($_REQUEST['__sr__'])) {
            $redirectUri = urldecode($_REQUEST['__sr__']);
            $redirectData = urldecode($_REQUEST['__rdd__']);

            $arrayUrl = explode('/',$redirectUri);
            $notifyData = array('referer' => $redirectData);
            $data = array_merge($data, $notifyData);

            $result = new \stdClass();
            $result->type = 'twitter.connect.' . ($success ? 'success' : 'failure');
            $result->msg = json_encode($data);

            $redirectUri .= (preg_match('/\?/', $redirectUri) ? '&' : '?') . '__st__=' . urlencode(json_encode($result));
            return Redirect::to($redirectUri);
        }

        View::share('msg', $msg);
        View::share('type', 'twitter');
        View::share('success', $success);
        return Response::view('social-callback');
    }
}