<?php
/**
 * EGroupware Api: Support for Sieve scripts via JMAP
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Sieve;

use EGroupware\Api;
use EGroupware\Api\Translation;
use EGroupware\Api\Mail;

/**
 * Support for Sieve scripts via JMAP
 *
 * Constructor and setters will throw exceptions for connection, login or other errors.
 *
 * retrieveRules and getters will not throw an exception if there's no script currently.
 *
 * Most methods incl. constructor accepts a script-name, but by default the current active script is used
 * and if there's no script Sieve::DEFAULT_SCRIPT_NAME.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9661.html RFC 9661: The JSON Meta Application Protocol (JMAP) for Sieve Scripts
 */
class Jmap implements Connection
{
	use Logic;

	/**
	 * How often an upload answered with 401 is tried in total, see installScript()
	 */
	const UPLOAD_401_TRIES = 4;

	/**
	 * @var int microseconds to wait after a 401 before the next try, multiplied by the number of the failed try
	 */
	public static int $upload_401_delay_us = 1500000;

	/**
	 * @var Mail\Jmap\Http
	 */
	protected $jmap;

	/**
	 * Using parameter for JMAP Sieve calls
	 */
	const USING = ["urn:ietf:params:jmap:core", "urn:ietf:params:jmap:sieve"];

	/**
	 * JMAP accountId the scripts belong to
	 *
	 * @var string
	 */
	protected $accountId;

	/**
	 * Constructor
	 *
	 * @param array|Mail\Imap\Jmap|Mail\Jmap\Http $params =[] JMAP object or params array to instantiate it,
	 *  or a JMAP client, eg. an admin one, together with $accountId of the account, which scripts to use
	 * @param string|null $accountId =null default accountId of the JMAP client
	 */
	function __construct($params=[], ?string $accountId=null)
	{
		if (is_a($params, Mail\Jmap\Http::class))
		{
			$this->jmap = $params;
		}
		elseif (is_a($params, Mail\Imap\Jmap::class))
		{
			$this->jmap = $params->jmapClient();
		}
		else
		{
			$this->jmap = (new Mail\Imap\Jmap($params))->jmapClient();
		}
		$this->accountId = $accountId ?? $this->jmap->accountId;
		$this->displayCharset	= Translation::charset();
	}

	/**
	 * Returns the list of scripts on the server.
	 *
	 * @throws \Exception
	 * @return array  An array with the list of script-names in the first element,
	 *                the active script-name in the second element and
	 *                the full list as returned by JMAP as a third element: array or array with values for keys id, name, isActive and blobId
	 */
	public function listScripts()
	{
		$scripts = [];
		$activeScript = null;
		$list = [];
		try
		{
			$response = $this->jmap->jmapCall([
				['SieveScript/get', [
					'accountId' => $this->accountId,
				], "0"],
			], self::USING);
			$list = $response['methodResponses'][0][1]['list'];
			foreach ($list as $script)
			{
				$scripts[] = $script['name'];
				if ($script['isActive'])
				{
					$activeScript = $script['name'];
				}
			}
		}
		// broad on purpose: an unexpected non-array response (eg. the server rejecting
		// SieveScript/get entirely) raises a \TypeError, not an \Exception, on the array-access
		// above - found live 2026-08-24 saving a freshly created JMAP account, where it crashed
		// the whole save instead of being treated like "no script yet" as intended below
		catch (\Throwable $e) {
			// ignore not existing script
			$list = [];
		}
		return [$scripts, $activeScript, $list];
	}

	/**
	 * Get the name of the active script
	 *
	 * @return string
	 * @throw \Exception if no active script found
	 */
	function getActive()
	{
		return $this->listScripts()[1] ?? throw new \Exception('NO active script found');
	}

	/**
	 * Retrieve rules, vacation, notifications and return Script object to update them
	 *
	 * @param string $_scriptName
	 * @return Script
	 */
	function retrieveRules($_scriptName=null)
	{
		if (!$_scriptName)
		{
			// query the active script from Sieve server
			if (empty($this->scriptName))
			{
				try {
					$this->scriptName = $this->getActive();
				}
				catch(\Exception $e) {
					unset($e);	// ignore NOTEXISTS exception
				}
				if (empty($this->scriptName))
				{
					$this->scriptName = self::DEFAULT_SCRIPT_NAME;
				}
			}
			$_scriptName = $this->scriptName;
		}
		$script = new Script($_scriptName);

		try {
			$script->retrieveRules($this);
		}
		catch (\Exception $e) {
			unset($e);	// ignore not found script exception
		}
		$this->script =& $script->script;
		$this->rules =& $script->rules;
		$this->vacation =& $script->vacation;
		$this->emailNotification =& $script->emailNotification; // Added email notifications

		return $script;
	}

	/**
	 * Retrieves a script.
	 *
	 * @param string $scriptname The name of the script to be retrieved.
	 *
	 * @throws \Exception
	 * @return string  The script.
	 */
	public function getScript($scriptname)
	{
		[$script] = array_filter($this->listScripts()[2], fn($script) => $script['name'] == $scriptname);
		if (empty($script))
		{
			throw new \Exception('Script '.$scriptname.' not found');
		}
		$url = strtr($this->jmap->downloadUrl, [
			'{accountId}' => $this->accountId,
			'{name}' => $scriptname,
			'{blobId}' => $script['blobId'],
			'{type}' => 'application/sieve',
		]);
		return $this->jmap->api($url);
	}

	/**
	 * Adds a script to the server.
	 *
	 * @param string  $scriptname Name of the script.
	 * @param string  $script     The script content.
	 * @param boolean $makeactive Whether to make this the active script.
	 *
	 * @throws \Exception
	 */
	public function installScript($scriptname, $script, $makeactive = false)
	{
		// array_filter() keeps the keys: [$found] = ... would only look at key 0 and miss a script, which is not the first one
		$found = current(array_filter($this->listScripts()[2], fn($script) => $script['name'] == $scriptname)) ?: null;

		// upload script: Stalwart answers the first upload with a token, which was just issued (impersonation token of the
		// MServer), with 401, although the JMAP call before it was accepted, and the same upload works a moment later
		// (seen live on bsz-stockach.de 2026-11-25 for 27 of 27 forward accounts), so retry a 401 a few times
		for ($try = 1; ; ++$try)
		{
			try {
				$response = $this->jmap->api(strtr($this->jmap->uploadUrl, [
					'{accountId}' => $this->accountId,
				]), 'POST', $script, [
					'Content-Type' => 'application/sieve',
					'Content-Length' => strlen($script),
					'Accept' => 'application/json',
				]);
				break;
			}
			catch (Api\Exception\Http $e) {
				if ($e->getCode() !== 401 || $try >= self::UPLOAD_401_TRIES)
				{
					throw $e;
				}
				error_log(__METHOD__."('$scriptname') upload answered 401, retry $try of ".(self::UPLOAD_401_TRIES-1));
				usleep(static::$upload_401_delay_us * $try);
			}
		}
		if (empty($found))
		{
			$response2 = $this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->accountId,
					$op='create' => [
						'A' => [
							'name' => $scriptname,
							'blobId' => $response['blobId'] ?? throw new \Exception("Upload of script '$scriptname' did NOT return blobId"),
						],
					],
				] + ($makeactive ? [
					'onSuccessActivateScript' => '#A'
				] : []), "0"],
			], self::USING);
			if (isset($response2['methodResponses'][0][1]['created']['A']['name']) &&
					$response2['methodResponses'][0][1]['created']['A']['name'] !== $scriptname ||
				$makeactive && empty($response2['methodResponses'][0][1]['created']['A']['isActive']))
			{
				throw new \Exception('Error uploading '.$scriptname.': '.json_encode($response2));
			}
		}
		else
		{
			$response2 = $this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->accountId,
					$op='update' => [
						$found['id'] => [
							'blobId' => $response['blobId'] ?? throw new \Exception("Upload of script '$scriptname' did NOT return blobId"),
						] + ($makeactive && !$found['isActive'] ? [
							'isActive' => true,
						] : []),
					],
				], "0"],
			], self::USING);
			if (empty($response2['methodResponses'][0][1]['updated'][$found['id']]['blobId']) ||
				// the returned blobId is for some reason not the one updated, ignoring it for now
				// $response2['methodResponses'][0][1]['updated'][$found['id']]['blobId'] !== $response['blobId'] ||
				$makeactive && !$found['isActive'] && empty($response2['methodResponses'][0][1]['updated']['isActive']))
			{
				throw new \Exception('Error uploading '.$scriptname.': '.json_encode($response2));
			}
		}
	}

	/**
	 * Removes a script from the server.
	 *
	 * @param string $scriptname Name of the script.
	 *
	 * @throws \Exception
	 */
	public function removeScript($scriptname)
	{
		// listScripts()[2] is the full list, there is no [3]; array_filter() keeps keys, so use current()
		$script = current(array_filter($this->listScripts()[2], fn($script) => $script['name'] == $scriptname)) ?: null;

		if (!empty($script))
		{
			$this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->accountId,
					'destroy' => [$script['id']],
				] + (!empty($script['isActive']) ? ['onSuccessDeactivateScript' => true] : []), '5'],
			], self::USING);
		}
	}

	/**
	 * Checks if the server has space to store the script by the server.
	 *
	 * @param string  $scriptname The name of the script to mark as active.
	 * @param integer $size       The size of the script.
	 *
	 * @return boolean  True if there is space.
	 */
	public function hasSpace($scriptname, $size)
	{
		return !isset($this->jmap->accountCapabilities['urn:ietf:params:jmap:sieve']['maxSizeScript']) ||
			$size <= $this->jmap->accountCapabilities['urn:ietf:params:jmap:sieve']['maxSizeScript'];
	}

	/**
	 * Returns the list of extensions the server supports.
	 *
	 * @return array  List of extensions.
	 */
	public function getExtensions()
	{
		return $this->jmap->accountCapabilities['urn:ietf:params:jmap:sieve']['sieveExtensions'] ?? [];
	}

	/**
	 * Returns whether the server supports an extension.
	 *
	 * @param string $extension The extension to check.
	 *
	 * @throws \Exception
	 * @return boolean  Whether the extension is supported.
	 */
	public function hasExtension($extension)
	{
		return in_array($extension, $this->getExtensions());
	}
}