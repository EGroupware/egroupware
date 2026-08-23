<?php
/**
 * EGroupware API - Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 */

namespace EGroupware\Api\Exception;

use EGroupware\Api;

/**
 * A REST/HTTP client call (CalDAV\RestClientTrait::api()) failed
 *
 * @property-read string $method
 * @property-read string $request_uri
 * @property-read string|resource $request_body sent
 * @property-read array|null $response_headers lowercased header-name => value pairs
 * @property-read string|null $response
 */
class Http extends Api\Exception
{
	public readonly string $method;
	public readonly string $request_uri;
	public readonly string $request_body;
	public readonly ?array $response_headers;
	public readonly ?string $response;

	/**
	 * Constructor
	 *
	 * @param string $message
	 * @param int $code with code=0: opening http connection failed, code=HTTP status otherwise
	 * @param string $method
	 * @param string $uri
	 * @param string|array|resource $body request body sent
	 * @param array|null $response_headers =null lowercased header-name => value pairs
	 * @param string|null $response =null
	 */
	public function __construct(string $message, int $code, string $method, string $uri, $body, ?array $response_headers=null, ?string $response=null)
	{
		parent::__construct($message, $code);

		$this->method = strtoupper($method);
		$this->request_uri = $uri;
		if (!in_array($this->method, ['GET', 'DELETE']))
		{
			$this->request_body = is_array($body) ? json_encode($body) : (is_resource($body) ? (string)$body : $body);
		}
		else
		{
			$this->request_body = '';
		}
		$this->response_headers = $response_headers;
		$this->response = $response;
	}
}
