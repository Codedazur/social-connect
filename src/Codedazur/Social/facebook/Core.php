<?php namespace Codedazur\Social\Facebook;

/**
 * Wrapper for the Facebook API
 *
 * This class is a simple wrapper based around the already existing Facebook API class.
 * Used for wrapping the API with easy to use methods for basic facebook interaction, making it even easier to use.
 *
 * By Jimmy James <jimmy@codedazur.nl>
 */
use \Config;
class Core extends Api
{
	/**
	 * Get albumID from title. Will create a new album when it doesn't exist.
	 *
	 * @param string $albumName
	 * @param string $creationMessage
	 * @return int
	 */
	public function getAlbum($albumName = 'Untitled Album', $creationMessage = '')
	{
		try {
			$albums = $this->api('/me/albums');
			$albumID = null;

			foreach ($albums['data'] as $album) {
				// Format the album names to make sure we're not creating doubles with minor differences
				if (Utils::stringToUri($album['name']) == Utils::stringToUri($albumName)) {
					$albumID = $album['id'];
				}
			}

			if (is_null($albumID)) {
				$result = $this->api('/me/albums', 'post', array('message' => $creationMessage, 'name' => $albumName));

				$albumID = $result['id'];
			}

			return $albumID;
		} catch (ApiException $e) {
			trigger_error("Failed FacebookAPI interaction: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Adds a photo to an album
	 * @param int $albumID
	 * @param string $imagePath
	 * @param string $message
	 * @param array $args
	 * @return mixed
	 */
	public function addPhoto($albumID = null, $imagePath = '', $message = '', array $args = array())
	{
		$this->useFileUploadSupport();

		$url = is_null($albumID) ? '/me/photos' : '/' . $albumID . '/photos';

		$result = $this->api($url, 'post', array_merge(array('message' => $message, 'image' => '@' . realpath($imagePath)), $args));

		return $result;
	}

	/**
	 * Post a message to either yourself ('me') or another user ID
	 *
	 * @param string $userID
	 * @param string $message
	 * @param string $link
	 * @param string $picture
	 * @param string $name
	 * @param string $description
	 * @return bool|mixed[]
	 */
	public function post($userID = 'me', $message = '', $link = '', $picture = '', $name = '', $description = '')
	{
		try {
			$result = $this->api('/' . $userID . '/feed', 'post', array('message' => $message, 'link' => $link, 'picture' => $picture, 'name' => $name, 'description' => $description));

			return $result;
		} catch (ApiException $e) {
			trigger_error("Failed FacebookAPI interaction: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get user profile by ID. Defaults to 'me'.
	 *
	 * @param string $userID
	 * @return bool|mixed
	 */
	public function getProfile($userID = 'me')
	{
		try {
			$result = $this->api('/' . $userID . '', 'get');
			return $result;
		} catch (ApiException $e) {
			trigger_error("Failed FacebookAPI interaction: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get Long term access token, and swap it with the current
	 *
	 * @return bool|string
	 */
	public function getLongTermAccessToken()
	{
        $appId = Config::get('facebook.appID');
        $secret = Config::get('facebook.secret');
		$url = 'https://graph.facebook.com/oauth/access_token?client_id=' . $appId . '&client_secret=' . $secret . '&grant_type=fb_exchange_token&fb_exchange_token=' . $this->getAccessToken();
		$response = file_get_contents($url);
		parse_str($response);

		if (isset($access_token)) {
			$this->setAccessToken($access_token);
			return $access_token;
		}

		return false;
	}

	/**
	 * @param $photoID
	 * @param $userID
	 * @param int $x
	 * @param int $y
	 * @return mixed
	 */
	public function tagUser($photoID, $userID, $x = 0, $y = 0)
	{
		$result = $this->api('/' . $photoID . '/tags/' . $userID, 'post', array('x' => $x, 'y' => $y));
		return $result;
	}
}