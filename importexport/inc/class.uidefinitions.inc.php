<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

require_once('class.bodefinitions.inc.php');

/**
 * Userinterface to define {im|ex}ports
 *
 * @package importexport
 */
class uidefinitions
{
	const _debug = false;

	const _appname = 'importexport';

	public $public_functions = array(
		'edit' => true,
		'index' => true,
		'wizzard' => true,
		'import_definition' => true,
	);

	/**
	 * holds all available plugins
	 *
	 * @var array
	 */
	var $plugins;

	/**
	 * holds user chosen plugin after step20
	 *
	 * @var object
	 */
	var $plugin;

	/**
	 * xajax response object
	 *
	 * @var object
	 */
	var $response = true;

	function uidefinitions()
	{
		// we cant deal with notice and warnings, as we are on ajax!
		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
		$GLOBALS['egw']->translation->add_app(self::_appname);
		$GLOBALS['egw_info']['flags']['currentapp'] = self::_appname;

		$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		$this->etpl = new etemplate();
		$this->clock = html::image(self::_appname,'clock');
		$this->steps = array(
			'wizzard_step10' => lang('Choose an application'),
			'wizzard_step20' => lang('Choose a plugin'),
			'wizzard_step80' => lang('Which users are allowed to use this definition'),
			'wizzard_step90' => lang('Choose a name for this definition'),
			'wizzard_finish' => '',
		);
		//register plugins
		$this->plugins = import_export_helper_functions::get_plugins();
	}

	/**
	 * List defined {im|ex}ports
	 *
	 * @param array $content=null
	 */
	function index($content = null,$msg='')
	{
		$bodefinitions = new bodefinitions(array('name' => '*'));
		if (is_array($content))
		{
			if (isset($content['delete']))
			{
				$bodefinitions->delete(array_keys($content['delete'],'pressed'));
			}
			elseif(($button = array_search('pressed',$content)) !== false)
			{
				$selected = array_keys($content['selected'],1);
				if(count($selected) < 1 || !is_array($selected)) exit();
				switch ($button)
				{
					case 'delete_selected' :
						$bodefinitions->delete($selected);
						break;

					case 'export_selected' :
						$mime_type = ($GLOBALS['egw']->html->user_agent == 'msie' || $GLOBALS['egw']->html->user_agent == 'opera') ?
							'application/octetstream' : 'application/octet-stream';
						$name = 'importexport_definition.xml';
						header('Content-Type: ' . $mime_type);
						header('Content-Disposition: attachment; filename="'.$name.'"');
						echo $bodefinitions->export($selected);
						exit();

						break;

					default:
				}
			}

		}
		$etpl = new etemplate(self::_appname.'.definition_index');

		// we need an offset because of autocontinued rows in etemplate ...
		$definitions = array('row0');

		foreach ($bodefinitions->get_definitions() as $identifier) {
			$definition = new definition($identifier);
			$definitions[] = $definition->get_record_array();
			unset($definition);
		}
		$content = $definitions;
		return $etpl->exec( self::_appname.'.uidefinitions.index', $content, array(), $readonlys, $preserv );
	}

	function edit()
	{
		if(!$_definition = $_GET['definition'])
		{
			//close window
		}
		$definition = array('name' => $_definition);
		$bodefinitions = new bodefinitions();
		$definition = $bodefinitions->read($definition);
		$definition['edit'] = true;
		$this->wizzard($definition);
	}

	function wizzard($content = null, $msg='')
	{
		$GLOBALS['egw_info']['flags']['java_script'] .=
			"<script LANGUAGE='JavaScript'>
				function xajax_eT_wrapper_init() {
					window.resizeTo(document.documentElement.scrollWidth+20,document.documentElement.offsetHeight+40);
					window.moveTo(screen.availWidth/2 - window.outerWidth/2,
						screen.availHeight/2 - window.outerHeight/2);
				}
			</script>";

		$this->etpl->read('importexport.wizzardbox');
		$this->wizzard_content_template =& $this->etpl->children[0]['data'][1]['A'][2][1]['name'];

		if(is_array($content) &&! $content['edit'])
		{
			if(self::_debug) error_log('importexport.wizzard->$content '. print_r($content,true));
			// fetch plugin object
			if($content['plugin'] && $content['application'])
			{
				// we need to deal with the wizzard object if exists
				if (file_exists(EGW_SERVER_ROOT . '/'. $content['application'].'/importexport/class.wizzard_'. $content['plugin'].'.inc.php'))
				{
					require_once(EGW_SERVER_ROOT . '/'. $content['application'].'/importexport/class.wizzard_'. $content['plugin'].'.inc.php');
					$wizzard_plugin = 'wizzard_'.$content['plugin'];
				}
				else
				{
					$wizzard_plugin = $content['plugin'];
				}
				$this->plugin = is_object($GLOBALS['egw']->$wizzard_plugin) ? $GLOBALS['egw']->$wizzard_plugin : new $wizzard_plugin;

				// Global object needs to be the same, or references to plugin don't work
				if(!is_object($GLOBALS['egw']->uidefinitions) || $GLOBALS['egw']->uidefinitions !== $this) 
					$GLOBALS['egw']->uidefinitions =& $this;
			}
			// deal with buttons even if we are not on ajax
			if(isset($content['button']) && array_search('pressed',$content['button']) === false && count($content['button']) == 1)
			{
				$button = array_keys($content['button']);
				$content['button'] = array($button[0] => 'pressed');
			}

			// post process submitted step
			if(!key_exists($content['step'],$this->steps))
				$next_step = $this->plugin->$content['step']($content);
			else
				$next_step = $this->$content['step']($content);

			// pre precess next step
			$sel_options = $readonlys = $preserv = array();
			if(!key_exists($next_step,$this->steps))
			{
				$this->wizzard_content_template = $this->plugin->$next_step($content,$sel_options,$readonlys,$preserv);
			}
			else
			{
				$this->wizzard_content_template = $this->$next_step($content,$sel_options,$readonlys,$preserv);
			}

			$html = $this->etpl->exec(self::_appname.'.uidefinitions.wizzard',$content,$sel_options,$readonlys,$preserv,1);
		}
		else
		{
			// initial content
			$GLOBALS['egw']->js->set_onload("xajax_eT_wrapper_init();");
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][previous]');");

			$sel_options = $readonlys = $preserv = array();
			if($content['edit'])
				unset ($content['edit']);

			$this->wizzard_content_template = $this->wizzard_step10($content, $sel_options, $readonlys, $preserv);
			$html = $this->etpl->exec(self::_appname.'.uidefinitions.wizzard',$content,$sel_options,$readonlys,$preserv,1);
		}

		if(class_exists('xajaxResponse'))
		{
			$this->response = new xajaxResponse();

			if ($content['closewindow'])
			{
				$this->response->addScript("window.close();");
				$this->response->addScript("opener.location.reload();");
				// If Browser can't close window we display a "close" buuton and
				// need to disable normal buttons
				$this->response->addAssign('exec[button][previous]','style.display', 'none');
				$this->response->addAssign('exec[button][next]','style.display', 'none');
				$this->response->addAssign('exec[button][finish]','style.display', 'none');
				$this->response->addAssign('exec[button][cancel]','style.display', 'none');
			}
			$this->response->addAssign('contentbox', 'innerHTML', $html);
			if (isset($GLOBALS['egw']->js) && $GLOBALS['egw']->js->body['onLoad'])
			{
				$this->response->addScript($GLOBALS['egw']->js->body['onLoad']);
			}
			$this->response->addAssign('picturebox', 'style.display', 'none');
			$this->response->addScript("set_style_by_class('div','popupManual','display','inline');");

			return $this->response->getXML();
		}
		else
		{
			$GLOBALS['egw']->js->set_onload("document.getElementById('picturebox').style.display = 'none';");
			$GLOBALS['egw']->common->egw_header();
			echo '<div id="divMain">'."\n";
			echo '<div><h3>{Im|Ex}port Wizzard</h3></div>';
			// adding a manual icon to every popup
			if ($GLOBALS['egw_info']['user']['apps']['manual'])
			{
				$manual = new etemplate('etemplate.popup.manual');
				echo $manual->exec(self::_appname.'.uidefinitions.wizzard',$content,$sel_options,$readonlys,$preserv,1);
				unset($manual);
			}

			echo '<div id="contentbox">';
			echo $html;
			echo '</div></div>'."\n";
			echo '<style type="text/css">#picturebox { position: absolute; right: 27px; top: 24px; }</style>'."\n";
			echo '<div id="picturebox">'. $this->clock. '</div>';
			return;
		}
	}

	/**
	 * gets name of next step
	 *
	 * @param string  $curr_step
	 * @param int $step_width
	 * @return string containing function name of next step
	 */
	function get_step ($curr_step, $step_width)
	{
		/*if($content['plugin'] && $content['application']&& !is_object($this->plugin))
		{
			$plugin_definition =  $this->plugins[$content['application']][$content['plugin']]['definition'];
			if($plugin_definition) $this->plugin = new $plugin_definition;
		}*/
		if(is_object($this->plugin) && is_array($this->plugin->steps))
		{
			$steps = array_merge($this->steps,$this->plugin->steps);
			$steps = array_flip($steps); asort($steps);	$steps = array_flip($steps);
		}
		else
		{
			$steps = $this->steps;
		}
		$step_keys = array_keys($steps);
		$nn = array_search($curr_step,$step_keys)+(int)$step_width;
		return (key_exists($nn,$step_keys)) ? $step_keys[$nn] : false;
	}


	function wizzard_step10(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step10->$content '.print_r($content,true));

		// return from step10
		if ($content['step'] == 'wizzard_step10')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $this->get_step($content['step'],1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step10($content,$sel_options,$readonlys,$preserv);
			}

		}
		// init step10
		else
		{
			$content['msg'] = $this->steps['wizzard_step10'];
			foreach ($this->plugins as $appname => $options) $sel_options['application'][$appname] = lang($appname);
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][previous]');");
			$content['step'] = 'wizzard_step10';
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizzard_chooseapp';
		}

	}

	// get plugin
	function wizzard_step20(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step20->$content '.print_r($content,true));

		// return from step20
		if ($content['step'] == 'wizzard_step20')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					$content['type'] = $this->plugin instanceof iface_import_plugin ? 'import' : 'export';
					return $this->get_step($content['step'],1);
				case 'previous' :
					unset ($content['plugin']);
					$this->response->addScript("disable_button('exec[button][previous]');");
					return $this->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step20($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step20
		else
		{
			$content['msg'] = $this->steps['wizzard_step20'];
			foreach ($this->plugins[$content['application']] as $type => $plugins) {
				foreach($plugins as $plugin => $name) {
					$sel_options['plugin'][$plugin] = $name;
				}
			}
			$content['step'] = 'wizzard_step20';
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizzard_chooseplugin';
		}


	}

	// allowed users
	function wizzard_step80(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.uidefinitions::wizzard_step80->$content '.print_r($content,true));

		// return from step80
		if ($content['step'] == 'wizzard_step80')
		{
			$content['allowed_users'] = implode(',',$content['allowed_users']);

			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $this->get_step($content['step'],1);
				case 'previous' :
					return $this->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step80($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step80
		else
		{
			$content['msg'] = $this->steps['wizzard_step80'];
			$content['step'] = 'wizzard_step80';
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizzard_chooseallowedusers';
		}
	}

	// name
	function wizzard_step90(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.uidefinitions::wizzard_step90->$content '.print_r($content,true));

		// return from step90
		if ($content['step'] == 'wizzard_step90')
		{
			// workaround for some ugly bug related to readonlys;
			unset($content['button']['next']);
			switch (array_search('pressed', $content['button']))
			{
				case 'previous' :
					return $this->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step90($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step90
		else
		{
			$content['msg'] = $this->steps['wizzard_step90'];
			$content['step'] = 'wizzard_step90';
			$preserv = $content;
			unset ($preserv['button']);
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][next]');");
			return 'importexport.wizzard_choosename';
		}


	}

	function wizzard_finish(&$content)
	{
		if(self::_debug) error_log('importexport.uidefinitions::wizzard_finish->$content '.print_r($content,true));
		// Take out some UI leavings
		unset($content['msg']);
		unset($content['step']);
		unset($content['button']);

		$bodefinitions = new bodefinitions();
		$bodefinitions->save($content);
		// This message is displayed if browser cant close window
		$content['msg'] = lang('ImportExport wizard finished successfully!');
		$content['closewindow'] = true;
		return 'importexport.wizzard_close';
	}

	function import_definition($content='')
	{
		$bodefinitions = new bodefinitions();
		if (is_array($content))
		{
			$bodefinitions->import($content['import_file']['tmp_name']);
			// TODO make redirect here!
			return $this->index();
		}
		else
		{
			$etpl = new etemplate(self::_appname.'.import_definition');
			return $etpl->exec(self::_appname.'.uidefinitions.import_definition',$content,array(),$readonlys,$preserv);
		}
	}
}
