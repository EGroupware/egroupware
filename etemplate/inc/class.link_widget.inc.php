<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - InfoLog LinkTo Widget                 *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * eTemplate Extension: several widgets as user-interface for the link-class
	 *
	 * 1) link-to:     Widget to create links to an other entries of link-aware apps
	 *	If an id was set, this widgets creats the links without further interaction with the calling code.
	 *	If the entry does not yet exist, the widget returns an array with the new links in the id. After the
	 *	entry was successful create, bolink::link($app,$new_id,$arr) has to be called to create the links!
	 * 2) link-list:   Widget to shows the links to an entry and a Unlink Button for each link
	 * 3) link-string: comma-separated list of link-titles with a link to its view method, value is like get_links()
	 *
	 * $content[$name] = array(
	 *   'to_app'       => // I  string appname of the entry to link to
	 *   'to_id'        => // IO int id of the entry to link to, for new entries 0, returns the array with new links
	 *	// the following params apply only for the link-to widget!
	 *   'no_files'     => // I  boolean suppress attach-files, default no
	 *   'search_label' => // I  string label to use instead of search
	 *   'link_label'   => // I  string label for the link button, default 'Link'
	 *	 
	 * );
	 * This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function.
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class link_widget
	{
		/** 
		 * exported methods of this class
		 * @var array
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		/**
		 * availible extensions and there names for the editor
		 * @var array
		 */
		var $human_name = array(	// this are the names for the editor
			'link-to'     => 'LinkTo',
			'link-list'   => 'LinkList',
			'link-string' => 'LinkString'
		);
		var $debug = False;

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function link_widget($ui)
		{
			if (!is_object($GLOBALS['egw']->link))
			{
				$GLOBALS['egw']->link =& CreateObject('infolog.bolink');
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
			
			if ($cell['type'] == 'link-to' && ($cell['readonly'] || $readonlys))
			{
				//echo "<p>link-to is readonly, cell=".print_r($cell,true).", readonlys=".print_r($readonlys)."</p>\n";
				// readonly ==> omit the whole widget
				$cell = $tmpl->empty_cell();
				return;
			}
			if ($cell['type'] == 'link-string')
			{
				$str = '';
				if (is_array($value))
				{
					foreach ($value as $link)
					{
						$options = '';
						if (($popup = $this->link->is_popup($link['app'],'view')))
						{
							list($w,$h) = explode('x',$popup);
							$options = ' onclick="window.open(this,this.target,\'width='.(int)$w.',height='.(int)$h.',location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\'); return false;"';
						}
						$str .= ($str !== '' ? ', ' : '') . $tmpl->html->a_href(
							$tmpl->html->htmlspecialchars($this->link->title($link['app'],$link['id'])),
							'/index.php',$this->link->view($link['app'],$link['id'],$link),$options);
					}
				}
				$cell['type'] = 'html';
				$cell['readonly'] = True;	// is allways readonly
				$value = $str;

				return True;
			}
			if (!is_array($value))
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
			switch ($type = $cell['type'])
			{
			case 'link-to':
				$value['msg'] = '';
				if ($value['button'] == 'upload' && !empty($value['file']) && $value['file']['tmp_name'] != 'none')
				{
					$value = $extension_data;
					$value['remark'] = '';

					$tpl =& new etemplate('etemplate.link_widget.attach');
				}
				elseif ($value['button'] == 'search' && count($ids = $this->link->query($value['app'],$value['query'])))
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
					if (!$value['button'])
					{
						$extension_data = $value;
					}
					$value = array_merge($extension_data,$value);
					$value['options-app'] = $this->link->app_list();
					asort($value['options-app']);	// sort them alphabetic

					if ($value['button'] == 'search') $value['msg'] = lang('Nothing found - try again !!!');

					$tpl =& new etemplate('etemplate.link_widget.search');
					if ($value['search_label'])
					{
						$tpl->set_cell_attribute('app','label',$value['search_label']);
					}
				}
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
						$value[$row]['popup'] = $this->link->is_popup($link['app'],'view');
					}
				}
				break;
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

			$value = array_merge($extension_data,(array) $value);

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
					if (is_array($value['file']) && $value['to_app'])
					{
						$link_id = $this->link->link($value['to_app'],$value['to_id'],
							$this->link->vfs_appname,$value['file'],$value['remark']);
						if (!is_array($value['to_id']))
						{
							unlink($value['file']['tmp_name']);
						}
						if (isset($value['primary']) && !$value['anz_links'] )
						{
							$value['primary'] = $link_id;
						}
						unset($value['file']);
					}
					$extension_data = $value;
					$loop = True;
					break;

				case 'upload':		// need to rename file, as php deletes it otherwise
					if (is_array($value['file']) && !empty($value['file']['tmp_name']) &&
							$value['file']['tmp_name'] != 'none')
					{
						move_uploaded_file($value['file']['tmp_name'],$value['file']['tmp_name'].'+');
						$value['file']['tmp_name'] .= '+';
						$extension_data = $value;
					}
					else
					{
						unset($value['file']);
						$button = '';
					}
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
	}
