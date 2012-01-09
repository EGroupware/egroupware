<?php
/**
 * eGroupWare  eTemplate Extension - Link Widgets / UI for the link class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-10 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate Extension: several widgets as user-interface for the link-class
 *
 * All widgets use the link-registry, to "know" which apps use popups (and what size).
 * Participating apps need to register a proper "search_link" hook - see eTemplate-reference (LinkWidgets) for info.
 * If run in a popup and the app uses no popups, a target will be set, to open a new full decorated window.
 *
 * The class contains the following widgets:
 * - link: Show a link to one linked entry specified by an array with keys app, id and optional title,
 *         help-message and extra_args (array with additional get parameters)
 *         Optionally the application can be specified as option and the value can be just the id.
 * - link-to: Widget to create links to an other entries of link-aware apps
 *	If the variables $data['widget_id']['to_app'] = $app and $data['widget_id']['to_id'] = $entry_id
 *      are set, this widget creates the links without further interaction with the calling code.
 *	If the entry does not yet exist, the widget returns an array with the new links in the id. After the
 *	entry was successfuly created, bolink::link($app,$new_id,$arr) has to be called to create the links!
 * - link-list: Widget to show the links to an entry in a table with an unlink icon for each link. Needs the same
 *	pair of variables as link-to widget and needs to have the same id, as the data is shared with link-to.
 * - link-string: comma-separated list of link-titles with a link to its view method, value is like get_links()
 *	or array with keys to_app and to_id (widget calls then get_links itself)
 * - link-add:    Add a new entry of the select app, which is already linked to a given entry
 * - link-entry:  Allow to select an entry of a selectable or in options specified app
 * - link-apps:   Select an app registered in the link system, options: '' or 'add'
 *
 *<code>
 * $content[$name] = array(
 *   'to_app'       => // I  string appname of the entry to link to
 *   'to_id'        => // IO int id of the entry to link to, for new entries 0, returns the array with new links
 *	// the following params apply only for the link-to widget!
 *   'no_files'     => // I  boolean suppress attach-files, default no
 *   'show_deleted' => // I  Show links that are marked as deleted, being held for purge
 *   'search_label' => // I  string label to use instead of search
 *   'link_label'   => // I  string label for the link button, default 'Link'
 *  // optional only for the link-add widget and link-entry widget
 *   'extra'        => // I  array with extra parameters, eg. array('cat_id' => 15), or string to add in onclick search for link-entry
 *   			       //eg. ",values2url(this.form,'start,end,duration,participants,recur_type,whole_day')"
 *   'query'        => // I  preset for the query
 *   'current'      => // I  currently select id
 *  // optional for link-string:
 *   'only_app'     => // I  string with appname, eg. 'projectmananager' to list only linked projects
 *   'link_type'    => // I  string with sub-type key to list only entries of that type
 * );
 *</code>
 *
 * This widget is independent of the UI as it only uses etemplate-widgets and has therefore no render-function.
 */
class link_widget
{
	/**
	 * @var array exported methods of this class
	 */
	var $public_functions = array(
		'pre_process'  => True,
		'post_process' => True,
	);
	/**
	 * @var array availible extensions and there names for the editor
	 */
	var $human_name = array(
		'link'        => 'Link',
		'link-to'     => 'LinkTo',
		'link-list'   => 'LinkList',
		'link-string' => 'LinkString',
		'link-add'    => 'LinkEntry',
		'link-entry'  => 'Select entry',
		'link-apps'   => 'LinkApps',
	);
	/**
	 * @var boolean $debug switches debug-messages on and off
	 */
	var $debug = False;

	/**
	 * Flag that ajax_search needs to add onchange line
	 *
	 */
	const AJAX_NEED_ONCHANGE = 987;

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function link_widget($ui='')
	{

	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		$extension_data['type'] = $type = $cell['type'];
		$extension_data['needed'] = $cell['needed'];
		$help = $cell['help'] ? ($value['help'] ? $value['help'] : $cell['help']) : lang('view this linked entry in its application');

		if ((in_array($type,array('link-to','link-add','link-entry')) && !$value) && ($cell['readonly'] || $readonlys))
		{
			//echo "<p>link-to is readonly, cell=".print_r($cell,true).", readonlys=".print_r($readonlys).", value='$value'</p>\n";
			// readonly ==> omit the whole widget
			$value = '';
			$cell = $tmpl->empty_cell();
			$extension_data = null;
			return;
		}
		if (!is_array($value) && in_array($type,array('link-to','link-list','link-add')))
		{
			$value = array(
				'to_id' => $value,
				'to_app' => $GLOBALS['egw_info']['flags']['currentapp']
			);
		}
		if ($this->debug)
		{
			echo "<p>link_widget::pre_process($name,$value,".print_r($cell,true).",$readonlys,,)</p>\n";
			echo "<p>start: $cell[type][$name]::pre_process: value ="; _debug_array($value);
			echo "extension_data[$cell[type]][$name] ="; _debug_array($extension_data);
		}
		switch ($cell['type'])
		{
		case 'link':
			$cell['readonly'] = True;	// set it readonly to NOT call our post_process function
			$extension_data = null;
			$cell['no_lang'] = 1;
			$link = $target = $popup = '';
			if (!is_array($value) && $value && isset($GLOBALS['egw_info']['apps'][$cell['size']]))
			{
				$value = array(
					'id' => $value,
					'app' => $cell['size'],
				);
				$cell['size'] = '';
			}
			if ($value['app'] && $value['id'])
			{
				$view = egw_link::view($value['app'],$value['id']);
				$link = $view['menuaction']; unset($view['menuaction']);
				foreach($view as $var => $val)
				{
					$link .= '&'.$var.'='.$val;
				}
				if (isset($value['extra_args']))
				{
					foreach($value['extra_args'] as $var => $val)
					{
						$link .= '&'.$var.'='.$val;
					}
				}
				if (!($popup = egw_link::is_popup($value['app'],'view')) &&
					etemplate::$request->output_mode == 2)	// we are in a popup
				{
					$target = '_blank';
				}
				if (!$cell['help'])
				{
					$cell['help'] = $value['help'];
					$cell['no_lang'] = 2;
				}
			}
			elseif (!$value['title'])
			{
				$cell = $tmpl->empty_cell();
				$cell['readonly'] = True;	// set it readonly to NOT call our post_process function
				$extension_data = null;
				return;
			}
			$cell['type'] = 'label';
			//  size: [b[old]][i[talic]],[link],[activate_links],[label_for],[link_target],[link_popup_size],[link_title]
			list($cell['size']) = explode(',',$cell['size']);
			// Pass link through framework's handler
			if(!$popup) $link = str_replace(',','%2C',egw::link('/index.php?menuaction='.$link,false,$value['app']));
			$cell['size'] .= ','.$link.',,,'.$target.','.$popup.','.$value['extra_title'];
			$value = $value['title'] ? $value['title'] : egw_link::title($value['app'],$value['id']);
			return true;

		case 'link-string':
			$str = '';
			if ($value && !is_array($value) && $cell['size'])
			{
				$value = array('to_id' => $value);
				list($value['to_app'],$value['only_app']) = explode(',',$cell['size']);
			}
			if ($value['to_id'] && $value['to_app'])
			{
				$value = egw_link::get_links($value['to_app'],$value['to_id'],$only_app = $value['only_app'],'link_lastmod DESC',true, $value['show_deleted']);
				if ($only_app)
				{
					foreach($value as $key => $id)
					{
						$value[$key] = array(
							'id'  => $id,
							'app' => $only_app,
						);
					}
				}
			}
			if (is_array($value))
			{
				foreach ($value as $link)
				{
					$str .= ($str !== '' ? ', ' : '') . self::link2a_href($link,$help);
				}
			}
			$cell['type'] = 'html';
			$cell['readonly'] = True;	// set it readonly to NOT call our post_process function
			$extension_data = null;
			$value = $str;
			return True;

		case 'link-add':
			$apps = egw_link::app_list($type == 'link-add' ? 'add_app' : 'query');
			if (!$apps || !$value['to_id'] || is_array($value['to_id']))	// cant do an add without apps or already created entry
			{
				$cell = $tmpl->empty_cell();
				return;
			}
			asort($apps);	// sort them alphabetic
			$value['options-add_app'] = array();
			foreach($apps as $app => $label)
			{
				$link = $GLOBALS['egw']->link('/index.php',egw_link::add($app,$value['to_app'],$value['to_id'])+
					(is_array($value['extra']) ? $value['extra'] : array()));
				if (($popup = egw_link::is_popup($app,'add')))
				{
					list($w,$h) = explode('x',$popup);
					$action = "window.open('$link','_blank','width=$w,height=$h,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes');";
				}
				else
				{
					$action = "location.href = '$link';";
				}
				$value['options-add_app'][$action] = $label;
				// modify add_app default to the action used as value
				if (isset($value['add_app']) && $app == $value['add_app']) $value['add_app'] = $action;
			}
			$tpl = new etemplate('etemplate.link_widget.add');
			break;

		case 'link-to':
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;
			if ($value['search_label'] && $extension_data['search_label'] != $value['search_label']) $value['search_label'] = lang($value['search_label']);
			$extension_data = $value;
			$tpl = new etemplate('etemplate.link_widget.to');
			if ($value['link_label']) $tpl->set_cell_attribute('create','label',$value['link_label']);
			if ($value['search_label']) $tpl->set_cell_attribute('search','label',$value['search_label']);

			self::get_sub_types($cell, $value, $tpl);

			break;

		case 'link-list':
			$app = $value['to_app'];
			$id  = isset($extension_data['to_id']) ? $extension_data['to_id'] : $value['to_id'];
			if ($this->debug)
			{
				echo "<p>link-list-widget[$name].preprocess: value="; _debug_array($value);
			}
			if (!isset($value['title']))
			{
				$value['title'] = egw_link::title($app,$id);
			}
			$links = egw_link::get_links($app,$id,'','link_lastmod DESC',true, $value['show_deleted']);
			$value['anz_links'] = count($links);
			$extension_data = $value;

			if (!count($links))
			{
				$cell = $tmpl->empty_cell();
				$value = '';
				return True;
			}
			$value['link_list_format'] = $GLOBALS['egw_info']['user']['preferences']['common']['link_list_format'];
			$tpl = new etemplate('etemplate.link_widget.list');
			for($row=$tpl->rows-1; list(,$link) = each($links); ++$row)
			{
				$value[$row] = $link;
				$value[$row]['title'] = egw_link::title($link['app'],$link['id'],$link);
				if (!is_array($link['id']))
				{
					$value[$row]['view']  = egw_link::view($link['app'],$link['id'],$link);
					if (!($value[$row]['popup'] = egw_link::is_popup($link['app'],'view')) &&
						etemplate::$request->output_mode == 2)	// we are in a popup
					{
						$value[$row]['target'] = '_blank';		// we create a new window as the linked page is no popup
					}
				}
				if ($link['app'] == egw_link::VFS_APPNAME)
				{
					$value[$row]['target'] = '_blank';
					$value[$row]['label'] = 'Delete';
					$value[$row]['help'] = lang('Delete this file');
					if ($value['link_list_format'] != 'text')
					{
						$value[$row]['title'] = preg_replace('/: ([^ ]+) /',': ',$value[$row]['title']);	// remove mime-type, it's alread in the icon
					}
					$value[$row]['icon'] = egw_link::vfs_path($link['app2'],$link['id2'],$link['id'],true);
				}
				else
				{
					$value[$row]['icon'] = egw_link::get_registry($value[$row]['app'], 'icon');
					$value[$row]['label'] = 'Unlink';
					$value[$row]['help'] = lang('Remove this link (not the entry itself)');
					if(!egw_link::is_popup($link['app'],'view') && etemplate::$request->output_mode == 2)
					{
						// If link doesn't want a popup, make sure to open it in appropriate tab of main window
						$value[$row]['view'] = "javascript:if(typeof opener.top.framework != 'undefined') {
							opener.top.egw_appWindowOpen('{$link['app']}','".egw::link('/index.php',$value[$row]['view'])."');
						} else {
							opener.open('".egw::link('/index.php',$value[$row]['view'])."','".$value[$row]['target']."');
						}";
					}
				}
			}
			break;

		case 'link-entry':
			if ($cell['readonly'] || $readonlys)
			{
				if(!is_array($value))
				{
					if (strpos($value,':') !== false) list($app,$value) = explode(':',$value,2);
					$value = array('app' => $app ? $app : $cell['size'],'id' => $value);
				}
				$value = self::link2a_href($value,$help);
				$cell['type'] = 'html';
				$cell['readonly'] = true;
				$extension_data = null;
				return true;
			}
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;
			$tpl = new etemplate('etemplate.link_widget.entry');
			$options = $cell['size'] ? explode(',',$cell['size']) : array();
			$app = $extension_data['app'] = $options[0];
			$link_type = $extension_data['link_type'];
			// handle extra args for onclick like: values2url(this.form,'start,end,duration,participants,recur_type,whole_day')+'&exec[event_id]=
			if ( isset($value) && is_array($value) && isset($value['extra']) )
			{
				//echo '<p>extra='.htmlspecialchars($value['extra'])."</p>\n";
				//something like: values2url(this.form,'start,end,duration,participants,recur_type,whole_day')+'&exec[event_id]=
				$on_click_string =& $tpl->get_cell_attribute('search','onclick');
				$on_click_string = str_replace(');',','.$value['extra'].');',$on_click_string);
				//echo htmlspecialchars($on_click_string);
			}
			if ($value)	// show pre-selected entry in select-box and not the search
			{
				if (is_array($value))
				{
					if (isset($value['current']))
					{
						list($app,$id) = explode(':',$value['current'], 2);
						if ($app) unset($value['default_sel']);	// would overwrite $app!
					}
				}
				else
				{
					// add selected-entry plus "new search" to the selectbox-options
					if (!isset($app) || strpos($value,':') !== false)
					{
						list($app,$id) = explode(':',$value, 2);
					}
					else
					{
						$id = $value;
					}
				}
				$titles = array();
				foreach(explode(',',$id) as $id)
				{
					if ($id)
					{
						if (!($title = egw_link::title($app,$id)))
						{
							$title = $app.': #'.$id;
						}
						$titles[$id] = $title;
					}
				}
				if ($titles)
				{
					if ($cell['onchange']) $titles[0] = lang('Show all / cancel filter');
					$titles[''] = lang('new search').' ...';
					$selectbox =& $tpl->get_widget_by_name('id');
					$selectbox['sel_options'] = $titles;
					// remove link_hide class from select-box-line
					$span =& $tpl->get_cell_attribute('select_line','span');
					$span = str_replace('link_hide','',$span);
					// add link_hide class to search_line
					$span =& $tpl->get_cell_attribute('search_line','span');
					$span .= ' link_hide';
					unset($span);
				}
			}
			if ($extension_data['app'] && count($options) <= 1)	// no app-selection, using app given in first option
			{
				$tpl->disable_cells('app');
				$onchange =& $tpl->get_cell_attribute('search','onclick');
				$onchange = str_replace("document.getElementById(form::name('app')).value",'\''.$cell['size'].'\'',$onchange);
				unset($onchange);
			}

			// store now our values in extension_data to preserve them upon submits (after empty title submit for example)
			$extension_data['default'] = $value;

			// adding possibility to get a default selection on app select, use for resource in calendar edit.participant
			$value = array(
				'app'        => is_array($value) && isset($value['default_sel']) ? $value['default_sel'] : $app,
				'no_app_sel' => !!$extension_data['app'],
				'id'         => is_array($value) ? $value['current'] : $id,
				'query'      => is_array($value) ? $value['query'] : '',
				'blur'       => $cell['blur'] ? lang($cell['blur']) :
					(count($options) == 1 ? lang($app) : lang('Search')),
				'extra'      => $cell['onchange'] ? ','.self::AJAX_NEED_ONCHANGE : null,	// store flang for ajax_search, to display extra_line required by onchange
			);
			if ($cell['needed']) $value['class'] = 'inputRequired';

			if ($options)	// limit the app-selectbox to the given apps
			{
				$tpl->set_cell_attribute('app','type','select');
				$tpl->set_cell_attribute('app','no_lang',true);
				$apps = egw_link::app_list('query');
				asort($apps);	// sort them alphabetic
				foreach($apps as $app => $label)
				{
					if (!in_array($app,$options)) unset($apps[$app]);
				}
				$value['options-app'] = $apps;
			}

			self::get_sub_types($cell, $value, $tpl);

			break;

		case 'link-apps':
			$apps = egw_link::app_list($cell['size'] ? $cell['size'] : 'query');
			if (!$apps)	// cant do an add without apps or already created entry
			{
				$cell = $tmpl->empty_cell();
				return;
			}
			asort($apps);	// sort them alphabetic
			$cell['sel_options'] = $apps;
			$cell['no_lang'] = True;	// already translated
			$cell['type'] = 'select';
			return true;
		}
		$cell['size'] = $cell['name'];
		$cell['type'] = 'template';
		$cell['name'] = $tpl->name;
		$cell['obj'] =& $tpl;
		// keep the editor away from the generated tmpls
		$tpl->no_onclick = true;

		if ($this->debug)
		{
			echo "<p>end: $type"."[$name]::pre_process: value ="; _debug_array($value);
		}
		return True;	// extra Label is ok
	}

	/**
	 * return a_href to view a linked entry
	 *
	 * @param array $link array with values for keys 'id' and 'app'
	 * @param string $help=''
	 * @return string
	 */
	static function link2a_href(array $link,$help='')
	{
		if (($popup = egw_link::is_popup($link['app'],'view')))
		{
			list($w,$h) = explode('x',$popup);
			$options = ' onclick="window.open(this,this.target,\'width='.(int)$w.',height='.(int)$h.',location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\'); return false;"';
		}
		elseif (etemplate::$request->output_mode == 2 || 	// we are in a popup
			$link['app'] == egw_link::VFS_APPNAME ||		// or it's a link to an attachment
			($target = egw_link::get_registry($link['app'],'view_target')))	// or explicit target set
		{
			$options = ' target="'.($target ? $target : '_blank').'"';
		}
		else
		{
			// Pass link through framework's handler
			$href = str_replace(',','%2C',egw::link('/index.php',egw_link::view($link['app'],$link['id'],$link),$link['app']));
		}
		if ($help)
		{
			$options .= " onMouseOver=\"self.status='".addslashes(html::htmlspecialchars($help))."'; return true;\"";
			$options .= " onMouseOut=\"self.status=''; return true;\"";
		}
		return html::a_href(
			html::htmlspecialchars(egw_link::title($link['app'],$link['id'])),
			$href ? $href : egw_link::view($link['app'],$link['id'],$link),'',$options);
	}

	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * Only used by select-dow so far
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param etemplate &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		//echo "<p>link_widget::post_process('$name',value=".print_r($value,true).",ext=".print_r($extension_data,true).",$loop,,value_in=".print_r($value_in,true)."</p>\n";

		switch($extension_data['type'])
		{
			case 'link-entry':
				//error_log(__METHOD__.__LINE__.array2string(array('data'=>$value,'value in'=>$value_in,'extension_data'=>$extension_data,'source'=>function_backtrace())));
				if (!$value_in['id'] && $extension_data['needed'])
				{
					$tmpl->set_validation_error($name,lang('Field must not be empty !!!'),'');
					return true;
				}
				$defaultData = false;
				// beware: default may be something like Array([link_type] => [query] => [id] => ) so take care for id, in case it is empty AND needed
				if (is_array($extension_data['default']) && !empty($extension_data['default']))
				{
					// this may fail, if $extension_data['default'][0] is set on purpose
					foreach($extension_data['default'] as $k => $v)
					{
						if ($v) 
						{
							$defaultData=true; 
							if ($k==0 && !empty($value_in['id'])) // we have a valid incomming id, we intend to use that
							{
								$defaultData=false;
								continue;
							}
							break;
						}
					}
					if ($defaultData)
					{
						$value = $extension_data['default'];
						$value['current'] = $extension_data['app'] ? $value_in['id'] : $value_in['app'].':'.$value_in['id'];
						// we take care for id, in case it is empty AND needed
						if(empty($value['id']) && $extension_data['needed']) $value['id'] = $value['current'];
					}
				}
				if($defaultData === false)
				{
					// this was the line before the default opt, not sure it works well in all case
					$value = $extension_data['app'] ? $value_in['id'] : $value['app'].':'.$value_in['id'];
				}
				//error_log(__METHOD__.__LINE__.array2string(array('return'=>$value)));
				return true;

			case 'link-apps':
				if (!$value_in && $extension_data['needed'])
				{
					$tmpl->set_validation_error($name,lang('Field must not be empty !!!'),'');
					return true;
				}
				$value = $value_in;
				return !!$value;
		}
		$buttons = array('search','create','new','upload','attach');
		while (!$button && list(,$bname) = each($buttons))
		{
			$button = $value[$bname] ? $bname : '';
		}
		if (is_array($value['unlink']))
		{
			$button = 'unlink';
			list($unlink) = @each($value['unlink']);
		}
		unset($value[$button]);
		unset($value['msg']);
		unset($extension_data['msg']);

		if (is_array($extension_data))
		{
			$value = is_array($value) ? array_merge($extension_data,$value) : $extension_data;
		}
		if ($button && $this->debug)
		{
			echo "<p>start: link_widget[$name]::post_process: button='$button', unlink='$unlink', value ="; _debug_array($value);
		}
		switch ($button)
		{
			case 'create':
				if ($value['to_app'])						// make the link
				{
					$link_id = egw_link::link($value['to_app'],$value['to_id'],
						$value['app'],$value['id'],$value['remark']);
					$value['remark'] = $value['query'] = '';

					if (isset($value['primary']) && !$value['anz_links'] )
					{
						$value['primary'] = $link_id;
					}
				}
				// fall-trough
			case 'search':
			case 'new':
				$extension_data = $value;
				$loop = True;
				break;

			case 'attach':
				$name = preg_replace('/^exec\[([^]]+)\](.*)$/','\\1\\2',$name);	// remove exec prefix
				if (is_array($value['file']) && $value['to_app'] &&
					!empty($value['file']['tmp_name']) && $value['file']['tmp_name'] != 'none')
				{
					if (!$value['to_id'] || is_array($value['to_id']))	// otherwise the webserver deletes the file
					{
						if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
						{
							$new_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'egw_');
						}
						else
						{
							$new_file = $value['file']['tmp_name'].'+';
						}
						move_uploaded_file($value['file']['tmp_name'],$new_file);
						$value['file']['tmp_name'] = $new_file;
					}
					if (!($link_id = egw_link::link($value['to_app'],$value['to_id'],
						egw_link::VFS_APPNAME,$value['file'],$value['remark'])))
					{
						etemplate::set_validation_error($name.'[file]',lang('Error copying uploaded file to vfs!'));
					}
					else
					{
						$value['remark'] = '';

						if (isset($value['primary']) && !$value['anz_links'] )
						{
							$value['primary'] = $link_id;
						}
						unset($value['comment']);
						unset($value['file']);
					}
				}
				else
				{
					etemplate::set_validation_error($name.'[file]',lang('You need to select a file first!'));
				}
				$extension_data = $value;
				$loop = True;
				break;

			case 'unlink':
				if ($this->debug)
				{
					//echo "<p>unlink(link-id=$unlink,$value[to_app],$value[to_id])</p>\n";
					if (is_array($value['to_id'])) _debug_array($value['to_id']);
				}
				egw_link::unlink2($unlink,$value['to_app'],$value['to_id']);
				if (is_array($value['to_id']))
				{
					$extension_data['to_id'] = $value['to_id'];	// else changes from unlink get lost
				}
				$loop = True;
				break;
		}
		$value['button'] = $button;

		if ($this->debug)
		{
			echo "<p>end: link_widget[$name]::post_process: value ="; _debug_array($value);
		}
		return True;
	}

	/**
	*	Get sub-types for the current application
	*/
	private static function get_sub_types($cell, &$value, &$tpl) {
		// Get sub-types
		if($value['options-app']) {
			$apps = $value['options-app'];
		} else {
			$apps = egw_link::app_list($cell['size'] ? $cell['size'] : 'query');
			asort($apps);	// sort them alphabetic
		}

		$current_app = $value['app'] ? $value['app'] : key($apps);
		if(is_array(egw_link::$app_register[$current_app]['types'])) {
			foreach(egw_link::$app_register[$current_app]['types'] as $key => $settings) {
				$value['options-link_type'][$key] = $settings['name'];
			}
			$span =& $tpl->get_cell_attribute('type_box','span');
			$span = str_replace('type_hide','type_show',$span);
			unset($span);
		}
	}

	/**
	 * Ajax callback to search in $app for $pattern, result is displayed in $id
	 *
	 * Called via onClick from etemplate.link_widget.(to|entry)'s search button
	 *
	 * @param string $app app-name to search
	 * @param string $pattern search-pattern
	 * @param string $id_res id of selectbox to show the result
	 * @param string $id_hide id(s) of the search-box/-line to hide after a successful search
	 * @param string $id_show id(s) of the select-box/-line to show after a successful search
	 * @param string $id_input id of the search input-field
	 * @param string $etemplate_exec_id of the calling etemplate, to upate the allowed ids
	 * @param string $extra optionnal extra search arguments
	 * @return string xajax xml response
	 */
	static function ajax_search($app,$type,$pattern,$id_res,$id_hide,$id_show,$id_input,$etemplate_exec_id,$extra=array())
	{
		$extra_array = array();
		if (!empty($extra))
		{
			//parse $extra as a get url
			parse_str($extra,$extra_array) ;
			// securize entries as they were html encoded and so not checked on the first pass
			_check_script_tag($extra_array,'extra_array');
		}
		if ($pattern == lang('Search') || $pattern == lang($app)) $pattern = '';
		if (empty($extra_array))
		{
			$search = $pattern;
		}
		else
		{
			$extra_array['search']= $pattern;
			$search = $extra_array;
		}
		// open request
		if ($etemplate_exec_id) $request = etemplate_request::read($etemplate_exec_id);

		$response = new xajaxResponse();
		$options = array();
		//$args = func_get_args(); $response->addAlert("link_widget::ajax_search('".implode("',\n'",$args)."')\n calling link->query( $app , $search, $type )" );
		//$args = func_get_args(); error_log(__METHOD__."('".implode("','",$args)."')");
		if($type) {
			$options['type'] = $type;
		}
		if (!($found = egw_link::query($app,$search,$options)))       // ignore the blur-text
		{
			$GLOBALS['egw']->translation->add_app('etemplate');
			$response->addAlert(lang('Nothing found - try again !!!'));
			$response->addScript("document.getElementById('$id_input').select();");
		}
		else
		{
			$script = "var select = document.getElementById('$id_res');\nselect.options.length=0;\n";

			// check if we need to add extra line to produce an onchange to submit the form
			if (($data = $request->get_to_process($id_input)) && $data['maxlength'] == self::AJAX_NEED_ONCHANGE)
			{
				$script .= "opt = select.options[select.options.length] = new Option('".addslashes(lang('%1 entries found, select one ...',count($found)))."',' ');\n";
			}
			foreach($found as $id => $option)
			{
				if (!is_array($option)) $option = array('label' => $option);
				// xajax uses xml to transport the label, therefore we have to replace not only CR, LF
				// (not allowed unencoded in Javascript strings) but also all utf-8 C0 and C1 plus CR and LF
				$option['label'] = preg_replace('/[\000-\037\177-\237]/u',' ',$option['label']);

				$script .= "opt = select.options[select.options.length] = new Option('".addslashes($option['label'])."','".addslashes($id)."');\n";
				if (count($option) > 1)
				{
					foreach($option as $name => $value)
					{
						if ($name != 'label') $script .= "opt.$name = '".addslashes($value)."';\n";
					}
				}
			}
			$script .= "select.options[select.options.length] = new Option('".addslashes(lang('New search').' ...')."','');\n";
			foreach(explode(',',$id_show) as $id)
			{
				$script .= "document.getElementById('$id').style.display='inline';\n";
			}
			foreach(explode(',',$id_hide) as $id)
			{
				$script .= "document.getElementById('$id').style.display='none';\n";
			}
			//$response->addAlert($script);
			$response->addScript($script);
		}
		// store new allowed id's in the eT request
		if ($request)
		{
			$data = $request->get_to_process($id_res);
			//error_log($id_res.'='.array2string($data));
			$data['allowed'] = $found ? array_keys($found) : array();
			$request->set_to_process($id_res,$data);
			// update id, if request changed it (happens if the request data is stored direct in the form)
			if ($etemplate_exec_id != ($new_id = $request->id()))
			{
				$response->addAssign('etemplate_exec_id','value',$new_id);
			}
		}
		return $response->getXML();
	}

	/**
	 * Ajax callback to search for sub-types for $app, result is displayed in $id_res
	 *
	 * Called via onChange from etemplate.link_widget.(to|entry)'s app list
	 *
	 * @param string $app app-name to search
	 * @param string $id_res id of selectbox to show the result
	 * @param string $etemplate_exec_id of the calling etemplate, to upate the allowed ids
	 * @return string xajax xml response
	 */
	static function ajax_get_types($app,$id_res,$etemplate_exec_id)
	{
		// open request
		if ($etemplate_exec_id) $request = etemplate_request::read($etemplate_exec_id);

		$response = new xajaxResponse();
		//$args = func_get_args(); $response->addAlert("link_widget::ajax_search('".implode("',\n'",$args)."')\n calling link->query( $app , $search )" );
		//$args = func_get_args(); error_log(__METHOD__."('".implode("','",$args)."')");


		$script = "var select = document.getElementById('$id_res');\nselect.options.length=0;\n";
		if(is_array(egw_link::$app_register[$app]['types']))
		{
			$found = egw_link::$app_register[$app]['types'];
			foreach(egw_link::$app_register[$app]['types'] as $id => $option)
			{
				$option = array('label' => $option['name']);
				$script .= "opt = select.options[select.options.length] = new Option('".addslashes($option['label'])."','".addslashes($id)."');\n";
				if (count($option) > 1)
				{
					foreach($option as $name => $value)
					{
						if ($name != 'label') $script .= "opt.$name = '".addslashes($value)."';\n";
					}
				}
			}
			$script .= "document.getElementById('$id_res').parentNode.style.display='inline';\n";
		}
		else
		{
			$script .= "document.getElementById('$id_res').parentNode.style.display='none';\n";
		}
		$response->addScript($script);

		// store new allowed id's in the eT request
		if ($request)
		{
			$data = $request->get_to_process($id_res);
			//error_log($id_res.'='.array2string($data));
			$data['allowed'] = $found ? array_keys($found) : array();
			$request->set_to_process($id_res,$data);
			// update id, if request changed it (happens if the request data is stored direct in the form)
			if ($etemplate_exec_id != ($new_id = $request->id()))
			{
				$response->addAssign('etemplate_exec_id','value',$new_id);
			}
		}
		return $response->getXML();
	}
}
