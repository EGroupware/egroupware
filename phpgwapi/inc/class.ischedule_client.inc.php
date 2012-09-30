<?php
/**
 * EGroupware: iSchedule client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * iSchedule client: clientside of iSchedule
 *
 * @link https://tools.ietf.org/html/draft-desruisseaux-ischedule-01 iSchedule draft from 2010
 */
class ischedule_client
{
	/**
	 * Own iSchedule version
	 */
	const VERSION = '1.0';

	private $url;

	private $recipient;

	private $originator;

	/**
	 * Private key of originators domain
	 */
	private $dkim_private_key;

	/**
	 * Constructor
	 *
	 * @param string $recipient=null recipient email-address
	 * @param string $url=null ischedule url, if it should NOT be discovered
	 * @throws Exception in case of an error or discovery failure
	 */
	public function __construct($recipient, $url=null)
	{
		$this->recipient = $recipient;
		$this->originator = $GLOBALS['egw_info']['user']['account_email'];

		if (is_null($url))
		{
			list(,$domain) = explode('@', $recipient);
			$this->url = self::discover($domain);
		}
		else
		{
			$this->url = $url;
		}
	}

	const EMAIL_PREG = '/^([a-z0-9][a-z0-9._-]*)?[a-z0-9]@([a-z0-9](|[a-z0-9_-]*[a-z0-9])\.)+[a-z]{2,6}$/i';

	/**
	 * Set originator and (optional) DKIM private key
	 *
	 * @param string $originator
	 * @param string $dkim_private_key=null
	 * @throws Exception for invalid / not an email originator
	 */
	public function setOriginator($originator, $dkim_private_key=null)
	{
		if (!preg_match(self::EMAIL_PREG, $originator))
		{
			throw new Exception("Invalid orginator '$originator'!");
		}
		$this->originator = $originator;

		if (!is_null($dkim_private_key))
		{
			$this->dkim_private_key = $dkim_private_key;
		}
	}

	/**
	 * Discover iSchedule url of a given domain
	 *
	 * @param string $domain
	 * @return string discovered ischedule url
	 * @throws Exception in case of an error or discovery failure
	 */
	public static function discover($domain)
	{
		static $scheme2port = array(
			'https' => 443,
			'http' => 80,
		);

		$d = $domain;
		for($n = 0; $n < 3; ++$n)
		{
			if (!($records = dns_get_record($host='_ischedules._tcp.'.$d, DNS_SRV)) &&
				!($records = dns_get_record($host='_ischedule._tcp.'.$d, DNS_SRV)))
			{
				// try without subdomain(s)
				$parts = explode('.', $d);
				if (count($parts) < 3) break;
				array_shift($parts);
				$d = implode('.', $parts);
			}
		}
		if (!$records) throw new Exception("Could not discover iSchedule service for domain '$domain'!");

		// ToDo: do we need to use priority and weight
		$record = $records[0];

		$url = strpos($host, '_ischedules') === 0 ? 'https' : 'http';
		if ($scheme2port[$url] == $record['port'])
		{
			$url .= '://'.$record['target'];
		}
		else
		{
			$url .= '://'.$record['target'].':'.$record['port'];
		}
		$url .= '/.well-known/ischedule';

		return $url;
	}

	/**
	 * Post dkim signed message to recipients iSchedule server
	 *
	 * @param string $content
	 * @param string $content_type
	 * @return string
	 * @throws Exception with http status code and message, if server responds other then 2xx
	 */
	public function post_msg($content, $content_type)
	{
		$url_parts = parse_url($this->url);
		$headers = array(
			'Host' => $url_parts['host'].($url_parts['port'] ? ':'.$url_parts['port'] : ''),
			'iSchedule-Version' => self::VERSION,
			'Content-Type' => $content_type,
			'Originator' => $this->originator,
			'Recipient' => $this->recipient,
			'Content-Length' => bytes($content),
		);
		$headers['DKIM-Signature'] = $this->dkim_sign($headers, $content);
		$header_string = '';
		foreach($headers as $name => $value)
		{
			$header_string .= $name.': '.$value."\r\n";
		}
		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => $header_string,
		        //'timeout' => $timeout,	// max timeout in seconds
		        'content' => $content,
		    )
		);

		// need to suppress warning, if http-status not 2xx
		if (($response = @file_get_contents($this->url, false, stream_context_create($opts))) === false)
		{
			list(, $code, $message) = explode(' ', $http_response_header[0], 3);
			throw new Exception($message, $code);
		}
		return $response;
	}

	/**
	 * Calculate DKIM signature for headers and body using originators domains private key
	 *
	 * @param array $headers
	 * @param string $body
	 * @param string $type dkim-type
	 */
	public function dkim_sign(array $headers, $body, $type='calendar')
	{
		return 'dummy';
	}

	/**
	 * Capabilities
	 *
	 * @var array
	 */
	private $capabilities;

	/**
	 * Query capabilities of iSchedule server
	 *
	 * @param string $name=null name of capability to return, default null to return internal array with all capabilities
	 * @return mixed
	 * @throws Exception in case of an error or discovery failure
	 */
	public function capabilities($name=null)
	{
		if (!isset($this->capabilities))
		{
			$reader = new XMLReader();
			if (!$reader->open($this->url.'?query=capabilities'))
			{
				throw new Exception("Could not read iSchedule server capabilities $this->url!");
			}

			$this->capabilities = self::xml2assoc($reader);
			$reader->close();

			if (!isset($this->capabilities['query-result']) || !isset($this->capabilities['query-result']['capability-set']))
			{
				throw new Exception("Server returned invalid capabilities!");
			}
			$this->capabilities = $this->capabilities['query-result']['capability-set'];
			print_r($this->capabilities);
		}
		return $name ? $this->capabilities[$name] : $this->capabilities;
	}

	/**
	 * Parse capabilities xml into an associativ array
	 *
	 * @param XMLReader $xml
	 * @param &$target=array()
	 * @return mixed
	 */
	private static function xml2assoc(XMLReader $xml, &$target = array())
	{
		while ($xml->read())
		{
			switch ($xml->nodeType) {
				case XMLReader::END_ELEMENT:
					return $target;
				case XMLReader::ELEMENT:
					$name = $xml->name;
					$empty = $xml->isEmptyElement;
					$attr_name = $xml->getAttribute('name');
					if (($name_attr = $xml->getAttribute('name')))
					{
						$name = $attr_name;
					}
					if (isset($target[$name]))
					{
						if (!is_array($target[$name]))
						{
							$target[$name] = array($target[$name]);
						}
						$t = &$target[$name][count($target[$name])];
					}
					else
					{
						$t = &$target[$name];
					}
					if ($xml->isEmptyElement)
					{
						$t = '';
					}
					else
					{
						self::xml2assoc($xml, $t);
					}
					if ($xml->hasAttributes)
					{
						while($xml->moveToNextAttribute())
						{
							if ($xml->name != 'name')
					   	{
						   		$t['@'.$xml->name] = $xml->value;
							}
						}
					}
					break;
				case XMLReader::TEXT:
				case XMLReader::CDATA:
					$target = $xml->value;
			}
		}
		return $target;
	}

	/**
	 * Make private vars readable
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		return $this->$name;
	}
}
