<?php
/**
 * EGroupware - eTemplate serverside toolbar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;

/**
 * eTemplate button widget
 */
class Toolbar extends Etemplate\Widget
{
	/**
	 * Validate toolbar
	 *
	 * Readonly buttons can NOT be pressed!
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}

	/**
	 * Set up what we know on the server side.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$form_name = self::form_name($cname, $this->id, $expand);
			$value = &self::get_array(self::$request->modifications, $form_name, true);
			$value['is_admin'] = true;
		}
	}

	/**
	 * Set admin settings
	 *
	 * @param array $settings array of settings to be processed
	 */
	public static function ajax_setAdminSettings ($settings, $id, $app)
	{
		$response = \EGroupware\Api\Json\Response::get();
		// None admin users are not allowed to access
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$response->data(Lang('Permission denied! This is an administration only feature.'));
			exit();
		}
		foreach ($settings as $key => $setting)
		{
			switch ($key)
			{
				case 'actions':
					$GLOBALS['egw']->preferences->read_repository(true);
					$GLOBALS['egw']->preferences->add($app, $id, $setting, 'default');
					$GLOBALS['egw']->preferences->save_repository(true, 'default');
					$GLOBALS['egw']->preferences->read(true);
					break;
				case 'reset':
					if ($setting) $GLOBALS['egw']->preferences->change_preference($app, $id,'', null, 'user');
					break;
				default:
			}
		}
		$response->data(Lang('Settings saved.'));
	}

	/**
	 * Get default prefs
	 *
	 * @param string $app
	 * @param string $id
	 */
	public static function ajax_get_default_prefs ($app, $id)
	{
		$response = \EGroupware\Api\Json\Response::get();
		// None admin users are not allowed to access
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$response->data(Lang('Permission denied! This is an administration only feature.'));
			exit();
		}

		$response->data($GLOBALS['egw']->preferences->default_prefs($app, $id));
	}
}
