<?php
/**
 * EGroupware - eTemplate serverside of linking widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate Placeholder
 * Deals with listing & inserting placeholders, usually into Collabora
 */
class Placeholder extends Etemplate\Widget
{

	public $public_functions = array(
		'ajax_get_placeholders' => true,
		'ajax_fill_placeholder' => true
	);

	/**
	 * Constructor
	 *
	 * @param string|\XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml = '')
	{
		if($xml)
		{
			parent::__construct($xml);
		}
	}

	/**
	 * Set up what we know on the server side.
	 *
	 * Set the options for the application select.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand = null)
	{
	}

	/**
	 * Get the placeholders that match the given parameters.
	 * Default options will get all placeholders in a single request.
	 */
	public static function ajax_get_placeholders($apps = null, $group = null)
	{
		$placeholders = [];

		if(is_null($apps))
		{
			$apps = array_merge(
				['addressbook', 'user', 'general'],
				// We use linking for preview, so limit to apps that support links
				array_keys(Api\Link::app_list('query')),
				// Filemanager doesn't support links, but add it anyway
				['filemanager']
			);
		}

		foreach($apps as $appname)
		{
			$merge = Api\Storage\Merge::get_app_class($appname);
			switch($appname)
			{
				case 'user':
					$list = $merge->get_user_placeholder_list();
					break;
				case 'general':
					$list = $merge->get_common_placeholder_list();
					break;
				default:
					if(get_class($merge) === 'EGroupware\Api\Contacts\Merge' && $appname !== 'addressbook' || $placeholders[$appname])
					{
						// Looks like app doesn't support merging
						continue 2;
					}

					Api\Translation::load_app($appname, $GLOBALS['egw_info']['user']['preferences']['common']['lang']);
					$list = method_exists($merge, 'get_placeholder_list') ? $merge->get_placeholder_list() : [];
					break;
			}
			if(!is_null($group) && is_array($list))
			{
				$list = array_intersect_key($list, $group);
			}
			// Remove if empty
			foreach($list as $p_group => $p_list)
			{
				if(count($p_list) == 0)
				{
					unset($list[$p_group]);
				}
			}

			if($list)
			{
				$placeholders[$appname] = $list;
			}
		}

		$response = Api\Json\Response::get();
		$response->data($placeholders);
	}

	public function ajax_fill_placeholders($content, $entry)
	{
		$merge = Api\Storage\Merge::get_app_class($entry['app']);
		$err = "";

		switch($entry['app'])
		{
			case 'user':
				$entry = ['id' => $GLOBALS['egw_info']['user']['person_id']];
			// fall through
			default:
				$merged = $merge->merge_string($content, [$entry['id']], $err, 'text/plain', null, 'utf-8');
		}
		$response = Api\Json\Response::get();
		$response->data($merged);
	}

	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated =array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated = array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if(!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in =& self::get_array($content, $form_name);

			// keep values added into request by other ajax-functions, eg. files draged into htmlarea (Vfs)
			if((!$value || is_array($value) && !$value['to_id']) && is_array($expand['cont'][$this->id]) && !empty($expand['cont'][$this->id]['to_id']))
			{
				if(!is_array($value))
				{
					$value = array(
						'to_app' => $expand['cont'][$this->id]['to_app'],
					);
				}
				$value['to_id'] = $expand['cont'][$this->id]['to_id'];
			}

			// Link widgets can share IDs, make sure to preserve values from others
			$already = self::get_array($validated, $form_name);
			if($already != null)
			{
				$value = array_merge($value, $already);
			}
			// Automatically do link if user selected entry but didn't click 'Link' button
			$link = self::get_array($content, self::form_name($cname, $this->id . '_link_entry'));
			if($this->type == 'link-to' && is_array($link) && $link['app'] && $link['id'])
			{
				// Do we have enough information to link automatically?
				if(is_array($value) && $value['to_id'])
				{
					Api\Link::link($value['to_app'], $value['to_id'], $link['app'], $link['id']);
				}
				else
				{
					// Not enough information, leave it to the application
					if(!is_array($value['to_id']))
					{
						$value['to_id'] = array();
					}
					$value['to_id'][] = $link;
				}
			}

			// Look for files - normally handled by ajax
			$files = self::get_array($content, self::form_name($cname, $this->id . '_file'));
			if(is_array($files) && !(is_array($value) && $value['to_id']))
			{
				$value = array();
				if(is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$path = $GLOBALS['egw_info']['server']['temp_dir'] . '/';
				}
				else
				{
					$path = '';
				}
				foreach($files as $name => $attrs)
				{
					if(!is_array($value['to_id']))
					{
						$value['to_id'] = array();
					}
					$value['to_id'][] = array(
						'app' => Api\Link::VFS_APPNAME,
						'id'  => array(
							'name'     => $attrs['name'],
							'type'     => $attrs['type'],
							'tmp_name' => $path . $name
						)
					);
				}
			}
			$valid =& self::get_array($validated, $form_name, true);
			if(true)
			{
				$valid = $value;
			}
			//error_log($this);
			//error_log("   " . array2string($valid));
		}
	}
}
