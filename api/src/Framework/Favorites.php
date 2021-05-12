<?php
/**
 * EGroupware API - Favorites server-side
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray <ng@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;

/**
 * Favorites service-side:
 *
 * Favorites are generated on serverside by following code in apps sidebox hook:
 *
 * display_sidebox($appname, lang('Favorites'), Api\Framework\Favorites::favorite_list($appname));
 *
 * Clientside code resides in:
 * - api/js/jsapi/app_base.js
 * - api/js/etemplate/et2_widget_favorites.js
 *
 * Favorites are stored with prefix "favorite_" in app preferences.
 */
class Favorites
{
	/**
	 * Include favorites when generating the page server-side
	 *
	 * Use this function in your sidebox (or anywhere else, I suppose) to
	 * get the favorite list when a nextmatch is _not_ on the page.  If
	 * a nextmatch is on the page, it will update / replace this list.
	 *
	 * @param string $app application, needed to find preferences
	 * @param string $default preference name for default favorite, default "nextmatch-$app.index.rows-favorite"
	 *
	 * @return array with a single sidebox menu item (array) containing html for favorites
	 */
	public static function list_favorites($app, $default=null)
	{
		if (!$app)
		{
			return '';
		}

		if (!$default)
		{
			$default = "nextmatch-$app.index.rows-favorite";
		}

		// This target is used client-side to find & enable adding new favorites
		$target = 'favorite_sidebox_'.$app;

		/* @var $filters array an array of favorites*/
		$filters =  self::get_favorites($app);
		$is_admin = $GLOBALS['egw_info']['user']['apps']['admin'];
		$html = "<span id='$target' class='ui-helper-clearfix sidebox-favorites'><ul class='ui-menu ui-widget-content ui-corner-all favorites' role='listbox'>\n";

		$default_filter = $GLOBALS['egw_info']['user']['preferences'][$app][$default];
		if (!isset($default_filter) || !isset($filters[$default_filter]))
		{
			$default_filter = "blank";
		}

		// Get link for if there is no nextmatch - this is the fallback
		$registry = Api\Link::get_registry($app,'list');
		if (!$registry)
		{
			$registry = Api\Link::get_registry($app, 'index');
		}
		foreach($filters as $name => $filter)
		{
			//filter must not be empty if there's one, ignore it at the moment but it need to be checked how it got there in database
			if (!$filter)
			{
				error_log(__METHOD__.'Favorite filter "'.$name.'" is not supposed to be empty, it should be an array.  Skipping, more investigation needed. filter = '. array2string($filters[$name]));
				continue;
			}
			$li = "<li data-id='$name' data-group='{$filter['group']}' class='ui-menu-item' role='menuitem'>\n";
			$li .= '<a href="#" class="ui-corner-all">';
			$li .= "<div class='" . ((string)$name === (string)$default_filter ? 'ui-icon ui-icon-heart' : 'sideboxstar') . "'></div>".
				$filter['name'];
			$li .= ($filter['group'] != false && !$is_admin || $name === 'blank' ? "" :
				"<div class='ui-icon ui-icon-trash' title='" . lang('Delete') . "'></div>");
			$li .= "</a></li>\n";
			//error_log(__METHOD__."() $name, filter=".array2string($filter)." --> ".$li);
			$html .= $li;
		}

		// If were're here, the app supports favorites, so add a 'Add' link too
		$html .= "<li data-id='add' class='ui-menu-item' role='menuitem'><a href='javascript:app.$app.add_favorite()' class='ui-corner-all'>";
		$html .= Api\Html::image($app, 'new') . lang('Add current'). '</a></li>';

		$html .= '</ul></span>';

		return array(
			array(
				'no_lang' => true,
				'text'    => $html,
				'link'    => false,
				'icon'    => false,
			),
		);
	}

	/**
	 * Get preferenced favorites sorted list
	 *
	 * @param string $app Application name as string
	 *
	 * @return (array|boolean) An array of sorted favorites or False if there's no preferenced sorted list
	 *
	 */
	public static function get_fav_sort_pref ($app)
	{
		$fav_sorted_list = array();

		if (($fav_sorted_list = $GLOBALS['egw_info']['user']['preferences'][$app]['fav_sort_pref']))
		{
			return $fav_sorted_list;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get a list of actual user favorites
	 * The default 'Blank' favorite is not included here
	 *
	 * @param string $app Current application
	 *
	 * @return array Favorite information
	 */
	public static function get_favorites($app)
	{
		$favorites = array(
			'blank' => array(
				'name' => lang('No filters'),
				// Old
				'filter' => array(),
				// New
				'state' => array(),
				'group' => true
			)
		);
		$pref_prefix = 'favorite_';

		$sorted_list = array();
		$fav_sort_pref = self::get_fav_sort_pref($app);

		// Look through all preferences & pull out favorites
		foreach((array)$GLOBALS['egw_info']['user']['preferences'][$app] as $pref_name => $pref)
		{
			if(strpos($pref_name, $pref_prefix) === 0)
			{
				if(!is_array($pref)) continue;	// old favorite

				$favorites[(string)substr($pref_name,strlen($pref_prefix))] = $pref;
			}
		}
		if (is_array($fav_sort_pref))
		{
			foreach ($fav_sort_pref as $key)
			{
				$sorted_list[$key] = $favorites[$key];
			}
			$favorites = array_merge($sorted_list,$favorites);
		}
		return $favorites;
	}

	/**
	 * Create or delete a favorite for multiple users
	 *
	 * Current user needs to be an admin or it will just do nothing quietly
	 *
	 * @param string $app Current application, needed to save preference
	 * @param string $_name Name of the favorite
	 * @param string $action "add" or "delete"
	 * @param boolean|int|String $group ID of the group to create the favorite for, or 'all' for all users
	 * @param array $filters key => value pairs for the filter
	 * @return boolean Success
	 */
	public static function set_favorite($app, $_name, $action, $group, $filters = array())
	{
		// Only use alphanumeric for preference name, so it can be used directly as DOM ID
		$name = strip_tags($_name);
		$pref_name = "favorite_".$name;

		// older group-favorites have just true as their group and are not deletable, if we dont find correct group
		if ($group === true || $group === '1')
		{
			if (isset($GLOBALS['egw']->preferences->default[$app][$pref_name]))
			{
				$group = 'all';
			}
			else
			{
				foreach($GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true) as $gid)
				{
					$prefs = new Api\Preferences($gid);
					$prefs->read_repository();
					if (isset($prefs->user[$app][$pref_name]))
					{
						$group = $gid;
						break;
					}
				}
			}
		}
		if($group && $GLOBALS['egw_info']['apps']['admin'] && $group !== 'all')
		{
			$prefs = new Api\Preferences(is_numeric($group) ? $group : $GLOBALS['egw_info']['user']['account_id']);
		}
		else
		{
			$prefs = $GLOBALS['egw']->preferences;
		}
		$prefs->read_repository();
		$type = $group === "all" ? "default" : "user";
		//error_log(__METHOD__."('$app', '$name', '$action', ".array2string($group).", ...) pref_name=$pref_name, type=$type");
		if($action == "add")
		{
			$filters = array(
				// This is the name as user entered it, minus tags
				'name' => $name,
				'group' => $group ? $group : false,
				'state' => $filters
			);
			$pref_name = "favorite_".preg_replace('/[^A-Za-z0-9-_]/u','_',$name);
			$result = $prefs->add($app,$pref_name,$filters,$type);
			$pref = $prefs->save_repository(false,$type);

			// Update preferences client side, or it could disappear
			Api\Json\Response::get()->call('egw.set_preferences', (array)$pref[$app], $app);

			Api\Json\Response::get()->data(isset($result[$app][$pref_name]));
			return isset($result[$app][$pref_name]);
		}
		else if ($action == "delete")
		{
			$result = $prefs->delete($app,$pref_name, $type);
			$pref = $prefs->save_repository(false,$type);

			// Update preferences client side, or it could come back
			Api\Json\Response::get()->call('egw.set_preferences', (array)$pref[$app], $app);

			Api\Json\Response::get()->data(!isset($result[$app][$pref_name]));
			return !isset($result[$app][$pref_name]);
		}
	}

	/**
	 * Attributes for app-favorites below state containing account_ids
	 */
	protected static $app_favorite_attributes = array(
		'calendar' => 'owner',
		'addressbook' => 'filter',
		'tracker' => array('col_filter' => array('tr_assigned', 'tr_created')),
		'infolog' => array('col_filter' => array('info_owner', 'info_responsible')),
		'projectmanager' => array('col_filter' => array('pe_resources')),
		'timesheet' => array('col_filter' => array('ts_owner')),
	);

	/**
	 * Change account_id's in favorites stored in preferences
	 *
	 * Favorites have a preference name "favorite_*" with an attribute "group" and
	 * "state" (see $app_account_id_attributes above).
	 *
	 * Some apps use an implizit "favorite" to save their state called "index_state",
	 * calendar uses "saved_states".
	 *
	 * @param string $app
	 * @param array $ids2change from-id => to-id pairs
	 * @return integer number of changed ids
	 */
	public static function change_account_ids($app, array $ids2change)
	{
		$changes = 0;
		Api\Preferences::change_preference($app, '/^(index_state$|saved_states$|favorite_)/',
			function($attr, $old_value) use ($app, $ids2change, &$changes)
			{
				$value = is_array($old_value) ? $old_value : php_safe_unserialize($old_value);

				switch($attr)
				{
					case 'index_state':
						$changes += self::change_account_id($value, self::$app_favorite_attributes[$app], $ids2change);
						break;
					case 'saved_states':	// calendar
						$changes += self::change_account_id($value, self::$app_favorite_attributes[$app], $ids2change);
						break;
					default: // favorite_*
						$changes += self::change_account_id($value['state'], self::$app_favorite_attributes[$app], $ids2change);
						if ($value['group'] && isset($ids2change[$value['group']]))
						{
							$value['group'] = $ids2change[$value['group']];
							++$changes;
						}
				}

				// currently apps still use php-serialized index_state, can NOT transform to json
				if (!is_array($old_value))
				{
					$value = serialize($value);
				}
				//error_log("$app: change_account_ids_callback('$app', '$attr', ".array2string($old_value).", $owner) --> ".array2string($value));
				return $value;
			});

		return $changes;
	}

	/**
	 * Change account_id in (comma-separated) value
	 *
	 * @param array|string|int $value
	 * @param array|string $attrs_to_change
	 * @param array $ids2change
	 * @return int number of changes made
	 */
	protected static function change_account_id(&$value, $attrs_to_change, array $ids2change)
	{
		$changes = 0;

		if (!is_array($attrs_to_change))
		{
			if (!empty($value[$attrs_to_change]))
			{
				$vals = !is_array($value[$attrs_to_change]) ? explode(',', $value[$attrs_to_change]) : $value[$attrs_to_change];
				foreach($vals as &$v)
				{
					if (isset($ids2change[$v]))
					{
						$v = $ids2change[$v];
						$changes++;
					}
				}
				$value[$attrs_to_change] = !is_array($value[$attrs_to_change]) ? implode(',', $vals) : $vals;
			}
		}
		else
		{
			foreach($attrs_to_change as $key => $names)
			{
				if (is_int($key))
				{
					$changes += self::change_account_id($value, $names, $ids2change);
				}
				elseif (!empty($value[$key]))
				{
					$changes += self::change_account_id($value[$key], $names, $ids2change);
				}
			}
		}
		return $changes;
	}
}
