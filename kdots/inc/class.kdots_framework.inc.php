<?php

use EGroupware\Api;
use EGroupware\Api\Image;

class kdots_framework extends Api\Framework\Ajax
{
	/**
	 * Appname used for everything but JS includes
	 */
	const APP = 'kdots';
	/**
	 * Appname used to include javascript code
	 */
	const JS_INCLUDE_APP = 'kdots';

	/**
	 * Enable to use this template sets login.tpl for login page
	 */
	const LOGIN_TEMPLATE_SET = true;


	/**
	 * Get header as array to eg. set as vars for a template (from idots' head.inc.php)
	 *
	 * @param array $extra =array() extra attributes passed as data-attribute to egw.js
	 * @return array
	 */
	protected function _get_header(array $extra = array())
	{
		// Skip making a mess for iframe apps, they're on their own
		if($extra['check-framework'] == true)
		{
			$extra['check-framework'] = false;
			return [];
		}
		$data = parent::_get_header($extra);
		if($extra['navbar-apps'])
		{
			// Fix wrong icon type
			array_walk($extra['navbar-apps'], function (&$item, $key)
			{
				if(!$item['icon'] || !str_ends_with($item['icon'], 'svg'))
				{
					$item['icon'] = Image::find('api', 'navbar');
				}
			});
			$data['application-list'] = htmlentities(json_encode($extra['navbar-apps'], JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
			$open_app = current(array_filter($extra['navbar-apps'], function ($app)
			{
				return $app['active'] ?? false;
			})) ?? [];
			$data['open_app_name'] = $open_app['name'];
			$data['open_app_url'] = $open_app['url'];
		}
		if($data['open_app_name'] && !$this->sidebox_done)
		{
			$this->do_sidebox();
			$data['setSidebox'] = htmlentities(json_encode(static::$extra['setSidebox'], JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
		}
		return $data;
	}

	function topmenu(array $vars, array $apps)
	{
		$this->topmenu_items = $this->topmenu_info_items = array();

		parent::topmenu($vars, $apps);

		$vars['topmenu_items'] = "<sl-menu id='egw_fw_topmenu_items'>" . implode("\n", $this->topmenu_items) . "</sl-menu>";
		$vars['topmenu_info_items'] = '';
		foreach($this->topmenu_info_items as $id => $item)
		{
			switch($id)
			{
				case 'user_avatar':
					$vars['topmenu_info_items'] .= "<sl-dropdown class=\"topmenu_info_item\" id=\"topmenu_info_{$id}\" aria-label='" . lang("User menu") . "' tabindex='0'><div slot='trigger'>$item</div> {$vars['topmenu_items']}</sl-dropdown>";
					break;
				case 'darkmode':
					$vars['topmenu_info_items'] .= $item;
					break;
				default:
					$vars['topmenu_info_items'] .= '<button class="topmenu_info_item"' .
						(is_numeric($id) ? '' : ' id="topmenu_info_' . $id . '"') . '>' . $item . "</button>\n";
			}

		}
		$this->topmenu_items = $this->topmenu_info_items = null;

		return $vars;
	}

	/**
	 * Add info items to the topmenu template class to be displayed
	 *
	 * @param string $content Api\Html of item
	 * @param string $id = null
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_info_item($content, $id = null)
	{
		if(in_array($id, ['print_title']))
		{
			return;
		}

		if(strpos($content, 'menuaction=admin.admin_accesslog.sessions') !== false)
		{
			$content = preg_replace('/href="([^"]+)"/', "href=\"javascript:egw_link_handler('\\1','admin')\"", $content);
		}
		if($id)
		{
			$this->topmenu_info_items[$id] = $content;
		}
		else
		{
			$this->topmenu_info_items[] = $content;
		}
	}

	/**
	 * Add menu items to the topmenu template class to be displayed
	 *
	 * @param array $app application data
	 * @param mixed $alt_label string with alternative menu item label default value = null
	 * @param string $urlextra string with alternate additional code inside <a>-tag
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_item(array $app_data, $alt_label = null)
	{
		switch($app_data['name'])
		{
			case 'manual':
				$app_data['url'] = "javascript:callManual();";
				break;

			default:
				if(Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile')
				{
					break;
				}
				if(strpos($app_data['url'], 'logout.php') === false && substr($app_data['url'], 0, 11) != 'javascript:')
				{
					$app_data['url'] = "javascript:egw_link_handler('" . $app_data['url'] . "','" .
						(isset($GLOBALS['egw_info']['user']['apps'][$app_data['name']]) ?
							$app_data['name'] : 'about') . "')";
				}
		}
		$id = $app_data['id'] ? $app_data['id'] : ($app_data['name'] ? $app_data['name'] : $app_data['title']);
		$title = htmlspecialchars($alt_label ? $alt_label : $app_data['title']);
		$this->topmenu_items[] = '<sl-menu-item id="topmenu_' . $id . '" value="' . htmlspecialchars($app_data['url']) . '" title="' . $app_data['title'] . '">' .
			"<et2-image slot='prefix' src='${app_data['icon']}'></et2-image>" .
			$title .
			'</sl-menu-item>';
	}

	/**
	 * Set site-wide CSS like preferred font-size
	 *
	 * @return array
	 * @see Api\Framework::_get_css()
	 */
	public function _get_css()
	{
		$ret = parent::_get_css();

		$textsize = $GLOBALS['egw_info']['user']['preferences']['common']['textsize'] ?? '12';
		$ret['app_css'] .= "
			:root, :host, body, input {
				font-size: {$textsize}px;
				font-family: egroupware, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 
					Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
			}
		";

		return $ret;
	}


	/**
	 * Returns darkmode menu
	 *
	 * @return string
	 */
	protected static function _darkmode_menu()
	{
		$mode = $GLOBALS['egw_info']['user']['preferences']['common']['darkmode'] == 1 ? 'dark' : 'light';
		return '<egw-darkmode-toggle title="' . lang("%1 mode", $mode) . '" class="' .
			($mode == 'dark' ? 'darkmode_on' : '') . '"' . ($mode == 'dark' ? 'darkmode' : '') . '> </egw-darkmode-toggle>';
	}
}
