<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - InfoLog LinkList Widget               *
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
	@class linklist_widget
	@author ralfbecker
	@abstract widget that shows the links to an entry and a Unlink Button for each entry
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class linklist_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'LinkList';	// this is the name for the editor

		function linklist_widget($ui)
		{
			$this->link = CreateObject('infolog.bolink');
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			if (!is_array($value))
			{
				$value = array('to_id' => $value,'to_app' => $GLOBALS['phpgw_info']['flags']['currentapp']);
			}
			$app = $value['to_app'];
			$id  = $value['to_id'];
			//echo "<p>linklist_widget.preprocess: app='$app', id='$id', value="; _debug_array($value);

			if (!$value['title'])
			{
				$value['title'] = $this->link->title($to_app,$to_id);
			}
			$extension_data = $value;

			$links = $this->link->get_links($app,$id);
			if (!count($links))
			{
				$cell = $tmpl->empty_cell();
				return False;
			}
			$tpl = new etemplate('infolog.linklist_widget');
			for($row=$tpl->rows-1; list(,$link) = each($links); ++$row)
			{
				$value[$row] = $link;
				$value[$row]['title'] = $this->link->title($link['app'],$link['id']);
			}
			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = 'infolog.linklist_widget';
			$cell['obj'] = &$tpl;

			return True;	// extra Label is ok
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			list($unlink) = @each($value['unlink']);
			$pre_value = $extension_data;
			//echo "<p>linklist_widget.postprocess: app='$pre_value[app]', id='$pre_value[id]', unlink='$unlink', value="; _debug_array($value);

			if ($unlink)
			{
				$this->link->unlink($unlink,$pre_value['app'],$pre_value['id']);
				$loop = True;
				$value = $pre_value;
			}
		}
	}
