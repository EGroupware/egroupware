<?php
	/**
	 * eGroupWare  eTemplate Extension - Link Widgets / UI for the link class
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	/**
	 * eTemplate Extension: several widgets as user-interface for the link-class
	 *
	 * All widgets use the link-registry, to "know" which apps use popups (and what size).
	 * If run in a popup and the app uses no popups, a target will be set, to open a new full decorated window.
	 *
	 * The class contains the following widgets:
	 * - link: Show a link to one linked entry specified by an array with keys app, id and optional title and help-message
	 * - link-to: Widget to create links to an other entries of link-aware apps
	 *	If an id was set, this widgets creats the links without further interaction with the calling code.
	 *	If the entry does not yet exist, the widget returns an array with the new links in the id. After the
	 *	entry was successful create, bolink::link($app,$new_id,$arr) has to be called to create the links!
	 * - link-list: Widget to shows the links to an entry in a table with an unlink icon for each link
	 * - link-string: comma-separated list of link-titles with a link to its view method, value is like get_links() 
	 *	or array with keys to_app and to_id (widget calls then get_links itself)
	 * - link-add:    Add a new entry of the select app, which is already linked to a given entry
	 * - link-entry:  Allow to select an entry of a selectable or in options specified app
	 * - link-apps:   Select an app registerd in the link system, options: '' or 'add'
	 *
	 *<code>
	 * $content[$name] = array(
	 *   'to_app'       => // I  string appname of the entry to link to
	 *   'to_id'        => // IO int id of the entry to link to, for new entries 0, returns the array with new links
	 *	// the following params apply only for the link-to widget!
	 *   'no_files'     => // I  boolean suppress attach-files, default no
	 *   'search_label' => // I  string label to use instead of search
	 *   'link_label'   => // I  string label for the link button, default 'Link'
	 *  // optional only for the link-add widget
	 *   'extra'        => // I  array with extra parameters, eg. array('cat_id' => 15)
	 * );
	 *</code>
	 *
	 * This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function.
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	class link_widget
	{
		/** 
		 * @var array exported methods of this class
		 */
		var $public_functions = array(
			'pre_process'  => True,
			'post_process' => True,
			'ajax_search'  => True,
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
		 * reference to the link class
		 * 
		 * @var bolink
		 */
		var $link;

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function link_widget($ui='')
		{
			if (!is_object($GLOBALS['egw']->link))
			{
				$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
			}
			$this->link =& $GLOBALS['egw']->link;
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
			$help = $cell['help'] ? ($value['help'] ? $value['help'] : $cell['help']) : lang('view this linked entry in its application');

			if (($type == 'link-to' || $type == 'link-add') && ($cell['readonly'] || $readonlys))
			{
				//echo "<p>link-to is readonly, cell=".print_r($cell,true).", readonlys=".print_r($readonlys)."</p>\n";
				// readonly ==> omit the whole widget
				$cell = $tmpl->empty_cell();
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
				$cell['no_lang'] = 1;
				$link = $target = $popup = '';
				if ($value['app'] && $value['id'])
				{
					$view = $this->link->view($value['app'],$value['id']);
					$link = $view['menuaction']; unset($view['menuaction']);
					foreach($view as $var => $val)
					{
						$link .= '&'.$var.'='.$val;
					}
					if (!($popup = $this->link->is_popup($value['app'],'view')) &&
						$GLOBALS['egw_info']['etemplate']['output_mode'] == 2)	// we are in a popup
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
					return;			
				}
				$cell['type'] = 'label';
				//  size: [b[old]][i[talic]],[link],[activate_links],[label_for],[link_target],[link_popup_size]
				list($cell['size']) = explode(',',$cell['size']);
				$cell['size'] .= ','.$link.',,,'.$target.','.$popup;
				$value = $value['title'] ? $value['title'] : $this->link->title($value['app'],$value['id']);
				return true;

			case 'link-string':
				$str = '';
				if ($values['to_id'] && $values['to_app'])
				{
					$values = $this->link->get_links($values['to_app'],$values['to_id']);
				}
				if (is_array($value))
				{
					foreach ($value as $link)
					{
						$options .= " onMouseOver=\"self.status='".addslashes($tmpl->html->htmlspecialchars($help))."'; return true;\"";
						$options .= " onMouseOut=\"self.status=''; return true;\"";
						if (($popup = $this->link->is_popup($link['app'],'view')))
						{
							list($w,$h) = explode('x',$popup);
							$options = ' onclick="window.open(this,this.target,\'width='.(int)$w.',height='.(int)$h.',location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\'); return false;"';
						}
						elseif ($GLOBALS['egw_info']['etemplate']['output_mode'] == 2)	// we are in a popup
						{
							$options = ' target="_blank"';
						}
						$str .= ($str !== '' ? ', ' : '') . $tmpl->html->a_href(
							$tmpl->html->htmlspecialchars($this->link->title($link['app'],$link['id'])),
							'/index.php',$this->link->view($link['app'],$link['id'],$link),$options);
					}
				}
				$cell['type'] = 'html';
				$cell['readonly'] = True;	// set it readonly to NOT call our post_process function
				$value = $str;
				return True;

			case 'link-add':
				$apps = $this->link->app_list($type == 'link-add' ? 'add' : '');
				if (!$apps)	// cant do an add without apps or already created entry
				{
					$cell = $tmpl->empty_cell();
					return;
				}
				asort($apps);	// sort them alphabetic
				$value['options-app'] = array();
				foreach($apps as $app => $label)
				{
					$link = $GLOBALS['egw']->link('/index.php',$this->link->add($app,$value['to_app'],$value['to_id'])+
						(is_array($value['extra']) ? $value['extra'] : array()));
					if (($popup = $this->link->is_popup($app,'add')))
					{
						list($w,$h) = explode('x',$popup);
						$action = "window.open('$link','_blank','width=$w,height=$h,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes');";
					}
					else
					{
						$action = "location.href = '$link';";
					}
					$value['options-app'][$action] = $label;
				}
				$tpl =& new etemplate('etemplate.link_widget.add');
				break;

			case 'link-to':
				$GLOBALS['egw_info']['flags']['include_xajax'] = true;
				if ($value['search_label'] && $extension_data['search_label'] != $value['search_label']) $value['search_label'] = lang($value['search_label']);
				$extension_data = $value;
				$tpl =& new etemplate('etemplate.link_widget.to');
				if ($value['link_label']) $tpl->set_cell_attribute('create','label',$value['link_label']);
				if ($value['search_label']) $tpl->set_cell_attribute('search','label',$value['search_label']);
				/* old request code
				$value['msg'] = '';
				if ($value['button'] == 'search' && count($ids = $this->link->query($value['app'],$value['query'])))
				{
					$extension_data['app'] = $value['app'];

					$value = $extension_data;
					$value['options-id'] = $ids;
					$value['remark'] = '';

					$tpl =& new etemplate('etemplate.link_widget.create');
					if ($value['link_label'])
					{
						$tpl->set_cell_attribute('create','label',$value['link_label']);
					}
				}
				else
				{
					// error from search or upload
					$value['msg'] = $value['button'] == 'search' ? lang('Nothing found - try again !!!') : $extension_data['msg'];

					if (!$value['button'])
					{
						$extension_data = $value;
					}
					$value = array_merge($extension_data,$value);
					$value['options-app'] = $this->link->app_list();
					asort($value['options-app']);	// sort them alphabetic

					$tpl =& new etemplate('etemplate.link_widget.search');
					if ($value['search_label'])
					{
						$tpl->set_cell_attribute('app','label',$value['search_label']);
					}
					$tpl->set_cell_attribute('comment','onchange',"set_style_by_class('*','hide_comment','display',this.checked ? 'block' : 'none');");
					unset($value['comment']);
				}*/
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
					$value['title'] = $this->link->title($app,$id);
				}
				$links = $this->link->get_links($app,$id);
				$value['anz_links'] = count($links);
				$extension_data = $value;

				if (!count($links))
				{
					$cell = $tmpl->empty_cell();
					$value = '';
					return True;
				}
				$tpl =& new etemplate('etemplate.link_widget.list');
				$tpl->data[0]['A'] = $tmpl->data[0]['A'];	// set width of first col like the tmpl. calling us
				for($row=$tpl->rows-1; list(,$link) = each($links); ++$row)
				{
					$value[$row] = $link;
					$value[$row]['title'] = $this->link->title($link['app'],$link['id'],$link);
					if (!is_array($link['id']))
					{
						$value[$row]['view']  = $this->link->view($link['app'],$link['id'],$link);
						if (!($value[$row]['popup'] = $this->link->is_popup($link['app'],'view')) &&
							$GLOBALS['egw_info']['etemplate']['output_mode'] == 2)	// we are in a popup
						{
							$value[$row]['target'] = '_blank';		// we create a new window as the linked page is no popup
						}
					}
					if ($link['app'] == $this->link->vfs_appname)
					{
						$value[$row]['label'] = 'Delete';
						$value[$row]['help'] = lang('Delete this file');
					}
					else
					{
						$value[$row]['label'] = 'Unlink';
						$value[$row]['help'] = lang('Remove this link (not the entry itself)');
					}
				}
				break;

			case 'link-entry':
				$GLOBALS['egw_info']['flags']['include_xajax'] = true;
				$tpl =& new etemplate('etemplate.link_widget.entry');
				$options = $cell['size'] ? explode(',',$cell['size']) : array();
				$app = $extension_data['app'] = array_shift($options);
				if ($value)	// show pre-selected entry in select-box and not the search
				{
					// add selected-entry plus "new search" to the selectbox-options
					if (($app = $cell['size']))
					{
						$id = $value;
					}
					else
					{
						list($app,$id) = explode(':',$value);
					}
					$titles = array();
					foreach(explode(',',$id) as $id)
					{
						if ($id && ($title = $this->link->title($app,$id)))
						{
							$titles[$id] = $title;
						}
					}
					if ($titles)
					{
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
				if ($extension_data['app'])	// no app-selection, using app given in first option
				{
					$tpl->disable_cells('app');	
					$onchange =& $tpl->get_cell_attribute('search','onclick');
					$onchange = str_replace("document.getElementById(form::name('app')).value",'\''.$cell['size'].'\'',$onchange);
					unset($onchange);
				}
				$value = array(
					'app'        => $app,
					'no_app_sel' => !!$extension_data['app'],
					'id'         => $value,
				);
				if ($options)	// limit the app-selectbox to the given apps
				{
					$tpl->set_cell_attribute('app','type','select');
					$tpl->set_cell_attribute('app','no_lang',true);
					$apps = $this->link->app_list();
					asort($apps);	// sort them alphabetic
					foreach($apps as $app => $label)
					{
						if (!in_array($app,$options)) unset($apps[$app]);
					}
					$value['options-app'] = $apps;
				}
				break;
				
			case 'link-apps':
				$apps = $this->link->app_list($cell['size']);
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
		 * @param object &$tmpl the eTemplate the widget belongs too
		 * @param mixed &value_in the posted values (already striped of magic-quotes)
		 * @return boolean true if $value has valid content, on false no content will be returned!
		 */
		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			//echo "<p>link_widget::post_process('$name',value=".print_r($value,true).",ext=".print_r($extension_data,true).",$loop,,value_in=".print_r($value_in,true)."</p>\n";

			switch($extension_data['type'])
			{
				case 'link-entry':
					$value = $extension_data['app'] ? $value_in['id'] : $value['app'].':'.$value_in['id'];
					return !!$value_in['id'];
					
				case 'link-apps':
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
						$link_id = $this->link->link($value['to_app'],$value['to_id'],
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
					if (is_array($value['file']) && $value['to_app'] &&
						!empty($value['file']['tmp_name']) && $value['file']['tmp_name'] != 'none')
					{
						if (!$value['to_id'] || is_array($value['to_id']))	// otherwise the webserver deletes the file
						{
							move_uploaded_file($value['file']['tmp_name'],$value['file']['tmp_name'].'+');
							$value['file']['tmp_name'] .= '+';
						}
						$link_id = $this->link->link($value['to_app'],$value['to_id'],
							$this->link->vfs_appname,$value['file'],$value['remark']);
						$value['remark'] = '';

						if (isset($value['primary']) && !$value['anz_links'] )
						{
							$value['primary'] = $link_id;
						}
						unset($value['comment']);
						unset($value['file']);
					}
					else
					{
						$value['msg'] = 'You need to select a file first!';
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
					$this->link->unlink2($unlink,$value['to_app'],$value['to_id']);
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
		 * ajax callback to search in $app for $pattern, result is displayed in $id
		 *
		 * @param string $app app-name to search
		 * @param string $pattern search-pattern
		 * @param string $id_res id of selectbox to show the result
		 * @param string $id_hide id(s) of the search-box/-line to hide after a successful search
		 * @param string $id_show id(s) of the select-box/-line to show after a successful search
		 * @param string $id_input id of the search input-field
		 */
		function ajax_search($app,$pattern,$id_res,$id_hide,$id_show,$id_input)
		{
			$response = new xajaxResponse();
			//$args = func_get_args(); $response->addAlert("link_widget::ajax_search('".implode("','",$args)."')");
			
			if (!($found = $this->link->query($app,$pattern == lang('Search') ? '' : $pattern)))	// ignore the blur-text
			{
				$GLOBALS['egw']->translation->add_app('etemplate');
				$response->addAlert(lang('Nothing found - try again !!!'));
				$response->addScript("document.getElementById('$id_input').select();");
			}
			else
			{
				$script = "var select = document.getElementById('$id_res');\nselect.options.length=0;\n";
				foreach($found as $id => $title)
				{
					$script .= "select.options[select.options.length] = new Option('".addslashes($title)."','$id');\n";
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
			return $response->getXML();
		}
	}
