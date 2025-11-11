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
	 * @var Mail\Jmap
	 */
	protected $jmap;

	/**
	 * Constructor
	 *
	 * @param array|Mail\Imap\Jmap $params =[] JMAP object or params array to instantiate it
	 */
	function __construct($params=[])
	{
		if (is_a($params, Mail\Imap\Jmap::class))
		{
			$this->jmap = $params->jmapClient();
		}
		else
		{
			$this->jmap = (new Mail\Imap\Jmap($params))->jmapClient();
		}
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
		$list = $this->jmap->jmapCall([
			['SieveScript/get', [
				'accountId' => $this->jmap->accountId,
			], "0"],
		])['methodResponses'][0][1]['list'];
		foreach($list as $script)
		{
			$scripts[] = $script['name'];
			if ($script['isActive'])
			{
				$activeScript = $script['name'];
			}
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
			'{accountId}' => $this->jmap->accountId,
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
		[$found] = array_filter($this->listScripts()[2], fn($script) => $script['name'] == $scriptname);

		// upload script
		$response = $this->jmap->api(strtr($this->jmap->uploadUrl, [
			'{accountId}' => $this->jmap->accountId,
		]), 'POST', $script, [
			'Content-Type' => 'application/sieve',
			'Content-Length' => strlen($script),
			'Accept' => 'application/json',
		]);
		if (empty($found))
		{
			$response2 = $this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->jmap->accountId,
					$op='create' => [
						'A' => [
							'name' => $scriptname,
							'blobId' => $response['blobId'] ?? throw new \Exception("Upload of script '$scriptname' did NOT return blobId"),
						],
					],
				] + ($makeactive ? [
					'onSuccessActivateScript' => '#A'
				] : []), "0"],
			]);
			if (empty($response2['methodResponses'][0][1]['created']['A']['name']) ||
				$response2['methodResponses'][0][1]['created']['A']['name'] !== $scriptname ||
				$makeactive && empty($response2[0][1]['created']['A']['isActive']))
			{
				throw new \Exception('Error uploading '.$scriptname.': '.json_encode($response2));
			}
		}
		else
		{
			$response2 = $this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->jmap->accountId,
					$op='update' => [
						$found['id'] => [
							'blobId' => $response['blobId'] ?? throw new \Exception("Upload of script '$scriptname' did NOT return blobId"),
						] + ($makeactive && !$found['isActive'] ? [
							'isActive' => true,
						] : []),
					],
				], "0"],
			]);
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
		[$script] = array_filter($this->listScripts()[3], fn($script) => $script['name'] == $scriptname);

		if (!empty($script))
		{
			$this->jmap->jmapCall([
				['SieveScript/set', [
					'accountId' => $this->jmap->accountId,
					'onSuccessDeactivateScript' => true
				], '5'],
				['SieveScript/set', [
					'accountId' => $this->jmap->accountId,
					'destroy' => $script['blobId']
				], '6'],
			]);
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