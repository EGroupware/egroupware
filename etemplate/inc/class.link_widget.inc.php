<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - InfoLog LinkTo Widget               *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/*!
	@class link_widget
	@author ralfbecker
	@abstract link-to:   widget that enable you to make a link to an other entry of a link-aware app
	@abstract link-list: widget that shows the links to an entry and a Unlink Button for each entry
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no
	@discussion render-function.
	*/
	class link_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = array(	// this are the names for the editor
			'link-to'   => 'LinkTo',
			'link-list' => 'LinkList'
		);

		function link_widget($ui)
		{
			$this->link = CreateObject('infolog.bolink');
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			if (!is_array($value))
			{
				$value = array(
					'to_id' => $value,
					'to_app' => $GLOBALS['phpgw_info']['flags']['currentapp']
				);
			}
			//echo "<p>start: $cell[type]::pre_process: value ="; _debug_array($value);
			switch ($type = $cell['type'])
			{
			case 'link-to':
				if ($value['button'] == 'upload' && !empty($value['file']) && $value['file']['tmp_name'] != 'none')
				{
					$value = $extension_data;
					$value['remark'] = '';

					$tpl = new etemplate('etemplate.link_widget.attach');
				}
				elseif ($value['button'] == 'search' && count($ids = $this->link->query($value['app'],$value['query'])))
				{
					$extension_data['app'] = $value['app'];

					$value = $extension_data;
					$value['options-id'] = $ids;
					$value['remark'] = '';

					$tpl = new etemplate('etemplate.link_widget.create');
				}
				else
				{
					if (!$value['button'])
					{
						$extension_data = $value;
					}
					$value = array_merge($extension_data,$value);
					$value['options-app'] = $this->link->app_list();

					$tpl = new etemplate('etemplate.link_widget.search');
					$tpl->set_cell_attribute('msg','disabled',$value['button'] != 'search');
				}
				break;

			case 'link-list':
				$app = $value['to_app'];
				$id  = $value['to_id'];
				//echo "<p>link-list-widget.preprocess: value="; _debug_array($value);

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
				$tpl = new etemplate('etemplate.link_widget.list');
				$tpl->data[0]['A'] = $tmpl->data[0]['A'];	// set width of first col like the tmpl. calling us
				for($row=$tpl->rows-1; list(,$link) = each($links); ++$row)
				{
					$value[$row] = $link;
					$value[$row]['title'] = $this->link->title($link['app'],$link['id'],$link);
					$value[$row]['view']  = $this->link->view($link['app'],$link['id'],$link);
				}
				break;
			}
			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = $tpl->name;
			$cell['obj'] = &$tpl;

			//echo "<p>end: $type::pre_process: value ="; _debug_array($value);
			return True;	// extra Label is ok
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			$buttons = array('search','create','new','upload','attach');
			while (!$button && list(,$name) = each($buttons))
			{
				$button = $value[$name] ? $name : '';
			}
			if (is_array($value['unlink']))
			{
				$button = 'unlink';
				list($unlink) = @each($value['unlink']);
			}
			unset($value[$button]);

			$value = array_merge($extension_data,$value);
			
			if ($button && $this->debug)
			{
				echo "<p>start: link_widget::post_process: button='$button', unlink='$unlink', value ="; _debug_array($value);
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
							'vfs',$value['file'],$value['remark']);
						unlink($value['file']['tmp_name']);
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
					$this->link->unlink($unlink,$value['to_app'],&$value['to_id']);
					//echo "<p>unlink(link-id=$unlink,$value[to_app],$value[to_id])</p>\n";
					$loop = True;
					break;
			}
			$value['button'] = $button;
			//echo "<p>end: link_widget::post_process: value ="; _debug_array($value);
			return True;
		}
	}
