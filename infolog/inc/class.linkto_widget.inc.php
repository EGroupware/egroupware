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
	@class linkto_widget
	@author ralfbecker
	@abstract widget that enable you to make a link to an other entry of a link-aware app
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class linkto_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'LinkTo';	// this is the name for the editor

		function linkto_widget($ui)
		{
			$this->link = CreateObject('infolog.bolink');
		}

		function pre_process(&$cell,&$value,&$extension_data,&$readonlys)
		{
			$search = $value['search'] ? 1 : 0;
			$create = $value['create'] ? 1 : 0;
			//echo "<p>linkto_widget.preprocess: query='$value[query]',app='$value[app]',search=$search,create=$create</p>\n";

			if ($search && count($ids = $this->link->query($value['app'],$value['query'])))
			{
				$extension_data['app'] = $value['app'];

				$value = array(
					'app' => $value['app'],
					'options-id' => $ids,
					'remark' => ''
				);
				$next = 'create';
			}
			else
			{
				if (!$create)
				{
					$extension_data = $value;
				}
            $value = array(
					'app' => $value['app'],
					'options-app' => $this->link->app_list(),
					'query' => $value['query'],
					'msg' => $search ? 'Nothing found - try again !!!' : ''
				);
				$next = 'search';
			}
			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = "infolog.linkto_widget.$next";

			return True;	// extra Label is ok
		}

		function post_process(&$cell,&$value,&$extension_data,&$loop)
		{
			$search = $value['search'] ? 1 : 0;
			$create = $value['create'] ? 1 : 0;
			list($value['app']) = @$value['app'];	// no multiselection
			list($value['id'])  = @$value['id'];
			//echo "<p>linkto_widget.postprocess: query='$value[query]',app='$value[app]',id='$value[id]', search=$search,create=$create</p>\n";

			if ($create)
			{
				$value = array_merge($value,$extension_data);
				if ($value['to_app'])						// make the link
				{
					$this->link->link($value['to_app'],$value['to_id'],$value['app'],$value['id'],$value['remark']);
					echo "<p>linkto($value[app],$value[id],'$value[remark]')</p>\n";
				}
			}
			$loop = $search || $create;
		}
	}