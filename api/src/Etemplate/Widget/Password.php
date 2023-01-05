<?php
/**
 * EGroupware - eTemplate serverside textbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api\Auth;
use EGroupware\Api\Mail\Credentials;
use EGroupware\Api;
use XMLReader;

/**
 * eTemplate password widget
 *
 * passwords are not sent to client, instead a number of asterisks is send and replaced again!
 *
 * User must authenticate before password is decrypted & sent
 */
class Password extends Etemplate\Widget\Textbox
{
	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml)
	{
		parent::__construct($xml);
	}

	/**
	 * Set up what we know on the server side.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name);
		$plaintext = !empty($this->attrs['plaintext']) && !in_array(
			self::expand_name($this->attrs['plaintext'], $expand['c'] ?? null, $expand['row'] ?? null, $expand['c_'] ?? null, $expand['row_'] ?? null, $expand['cont']),
			['false', '0']);

		if (!empty($value))
		{
			$preserv =& self::get_array(self::$request->preserv, $form_name, true);
			$preserv = (string)$value;

			// only send password (or hash) to client-side, if explicitly requested
			if (!empty($value) && (!array_key_exists('viewable', $this->attrs) || !in_array($this->attrs['viewable'], ['1', 'true', true], true)))
			{
				$value = str_repeat('*', strlen($preserv));
			}
		}
	}

	/**
	 * Validate input
	 *
	 * We check if the password is unchanged or if the new value is the decrypted
	 * version of the current value to avoid unneeded changes
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param array $expand=array values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$plaintext = !in_array(self::expand_name($this->attrs['plaintext'],$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']),
			['false', '0']);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			// Non-viewable passwords are not transmitted back to client (just asterisks)
			// therefore we need to replace it again with preserved value
			$preserv = self::get_array(self::$request->preserv, $form_name);
			if ($value == str_repeat('*', strlen($preserv)))
			{
				$value = $preserv;
			}
			else if ($value_in && !$plaintext && $preserv && $value_in == Credentials::decrypt(array('cred_password' => $preserv,'cred_pw_enc' => Credentials::SYSTEM_AES)))
			{
				// Don't change if they submitted the decrypted version
				$value = $preserv;
			}
			else if ($value_in && !$plaintext && $value_in !== $preserv)
			{
				// Store encrypted
				$encryption = null;
				$value = Credentials::encrypt($value_in, 0, $encryption);
			}

			if ((string)$value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
			}

			if (isset($value))
			{
				self::set_array($validated, $form_name, $value);
				//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
			}
		}
	}

	/**
	 * Suggest a password
	 */
	public static function ajax_suggest($size = 12)
	{
		$config = Api\Config::read('phpgwapi');
		$size = max((int)$size, (int)$config['force_pwd_length'], 6);
		$password = Auth::randomstring($size, $config['force_pwd_strength'] == 4);

		$response = \EGroupware\Api\Json\Response::get();
		$response->data($password);
	}

	/**
	 * Give up the password
	 */
	public static function ajax_decrypt($user_password, $password)
	{
		$response = \EGroupware\Api\Json\Response::get();
		$decrypted = '';

		if($GLOBALS['egw']->auth->authenticate($GLOBALS['egw_info']['user']['account_lid'],$user_password))
		{
			$decrypted = Credentials::decrypt(array('cred_password' => $password,'cred_pw_enc' => Credentials::SYSTEM_AES));
		}
		$response->data($decrypted);
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\Password', ['et2-password', 'passwd']);