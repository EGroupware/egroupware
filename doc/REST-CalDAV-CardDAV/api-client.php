<?php
/**
 * EGroupware - REST API client for PHP
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav/rest
 * @author Ralf Becker <rb-at-egroupware.org>
 * @copyright (c) 2024 by Ralf Becker <rb-at-egroupware.org>
 */

/* Example usage of this client:
require_once('/path/to/egroupware/doc/api-client.php');

if (PHP_SAPI !== 'cli')
{
	die('This script can only be run from the command line.');
}
$base_url = 'https://egw.example.org/egroupware/groupdav.php';
$authorization[parse_url($base_url, PHP_URL_HOST)] = 'Authorization: Basic '.base64_encode('sysop:secret');

$params = [
	'filters[info_status]' => 'archive',
];
$courses = [];
foreach(apiIterator('/infolog/', $params) as $infolog)
{
	echo json_encode($infolog, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    foreach($infolog['participants'] as $account_id => $participant)
    {
        if ($participant['roles']['owner'] ?? false)
        {
            echo json_encode($contact=api('/addressbook-accounts/'.$account_id),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
            break;
        }
    }

}
*/

/**
 * Iterate through API calls on collections
 *
 * This function only queries a limited number of entries (default 100) and uses sync-token to query more.
 *
 * @param string $url either path (starting with / and prepending global $base_url) or full URL
 * @param array& $params can contain optional "sync-token" (default="") and "nresults" (default=100) and returns final "sync-token"
 * @return Generator<array> yields array with additional value for key "@self" containing the key of the responses-object yielded
 * @throws JsonException|Exception see api
 */
function apiIterator(string $url, array &$params=[])
{
	while(true)
	{
		if (!isset($params['nresults']))
		{
			$params['nresults'] = 100;
		}
		if (!isset($params['sync-token']))
		{
			$params['sync-token']='';
		}
		$responses = api($url, 'GET', $params);
		if (!isset($responses['responses']))
		{
			throw new \Exception('Invalid respose: '.(is_scalar($responses) ? $responses : json_encode($responses)));
		}
		foreach($responses['responses'] as $self => $response)
		{
			$response['@self'] = $self;

			yield $response;
		}
		$params['sync-token'] = $responses['sync-token'] ?? '';
		if (empty($responses['more-results']))
		{
			return;
		}
	}
}

/**
 * Make an API call to given URL
 *
 * Authorization is added from global $authorization array indexed by host-name of $url or $base_url
 *
 * @param string $url either path (starting with / and prepending global $base_url) or full URL
 * @param string $method
 * @param string|array|resource $body for GET&DELETE this is added as query and must not be a resource/file-handle
 * @param array $header
 * @param array|null $response_header associative array of response headers, key 0 has HTTP status
 * @param int $follow how many redirects to follow, default 3, can be set to 0 to NOT follow
 * @return array|string array of decoded JSON or string body
 * @throws JsonException for invalid JSON
 * @throws Exception with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
 */
function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null, int $follow=3)
{
	global $base_url, $authorization;

    if ($url[0] === '/')
    {
        $url = $base_url . $url;
    }
	if (in_array(strtoupper($method), ['GET', 'DELETE']) && $body && !is_resource($body))
	{
		$url .= '?' . (is_array($body) ? http_build_query($body) : $body);
	}
	if (!($curl = curl_init($url)))
	{
		throw new Exception(curl_error($curl));
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	if ($follow > 0)
	{
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_MAXREDIRS, $follow);
	}

	switch (strtoupper($method))
	{
		case 'POST':
			curl_setopt($curl, CURLOPT_POST, true);
			break;
		case 'PUT':
		case 'DELETE':
		case 'PATCH':
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
			break;
		case 'GET':
			curl_setopt($curl, CURLOPT_HTTPGET, true);
			break;
	}
	$header = array_merge($header, ['User-Agent: '.basename(__FILE__, '.php'), $authorization[parse_url($url, PHP_URL_HOST)]]);
	if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']))
	{
        if (is_resource($body))
        {
            fseek($body, 0, SEEK_END);
	        curl_setopt($curl, CURLOPT_INFILESIZE, ftell($body));
            fseek($body, 0);
        }
        curl_setopt($curl, is_resource($body) ? CURLOPT_INFILE : CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
	}
    if (!array_filter($header, function($header)
    {
        return stripos($header, 'Accept:') === 0;
    }))
    {
        $header[] = 'Accept: application/json';
    }
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	$response_header = [];
	if (($response = curl_exec($curl)) === false)
	{
		throw new Exception(curl_error($curl), 0);
	}
    do {
	    [$rheader, $response] = explode("\r\n\r\n", $response, 2);
	    foreach (explode("\r\n", $rheader) as $line)
	    {
		    list($key, $value) = explode(':', $line, 2) + [null, null];
		    if (!isset($value))
		    {
			    $response_header[0] = $key;
		    }
		    else
		    {
			    $response_header[strtolower($key)] = trim($value);
		    }
	    }
	    [, $http_status] = explode(' ', $response_header[0], 2);
    }
    while ($http_status[0] === '3' && $follow && preg_match('#^HTTP/[\d.]+ \d+#', $response));

	if ($http_status[0] !== '2')
	{
		throw new Exception("Unexpected HTTP status code $http_status: $response", (int)$http_status);
	}
	if ($response !== '' && preg_match('#^application/([^+; ]+\+)?json(;|$)#', $response_header['content-type']))
	{
		return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
	}
	return $response;
}