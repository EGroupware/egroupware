<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog - eTemplate Widget to show the custom fields      *
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
	@class customfields_widget
	@author ralfbecker
	@abstract generates a template based on an array with definitions
	@discussion This widget has neither a render nor a post_process function as it only generates a template
	*/
	class customfields_widget
	{
		var $public_functions = array(
			'pre_process' => True
		);
		var $human_name = 'InfoLog custom fields';

		function customfields_widget($ui)
		{
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			if (!is_array($value))
			{
				$cell['type'] = 'label';
				return True;
			}
			$tpl = new etemplate;
			$tpl->init('*** generated custom fields for InfoLog','','',0,'',0,0);	// make an empty template

			$typ = $value['###typ###'];
			unset($value['###typ###']);

			//echo '<pre style="text-aling: left;">'; print_r($value); echo "</pre>\n";
			foreach($value as $name => $field)
			{
				if (!empty($field['typ']) && $field['typ'] != $typ)
				{
					continue;	// not for our typ
				}
				$row_class = 'row';
				$label = &$tpl->new_cell(++$n,'label',$field['label'],'',array(
					'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0
				));
				if (count($field['values']))	// selectbox
				{
					foreach($field['values'] as $key => $val)
					{
						if (substr($val = lang($val),-1) != '*')
						{
							$field['values'][$key] = $val;
						}
					}
					$input = &$tpl->new_cell($n,'select','','#'.$name,array(
						'sel_options' => $field['values'],
						'size'        => $field['rows'],
						'no_lang'     => True
					));
				}
				elseif ($field['rows'] > 1)		// textarea
				{
					$input = &$tpl->new_cell($n,'textarea','','#'.$name,array(
						'size' => $field['rows'].($field['len'] > 0 ? ','.intval($field['len']) : '')
					));
				}
				elseif (intval($field['len']) > 0)	// regular input field
				{
					list($max,$shown) = explode(',',$field['len']);
					$input = &$tpl->new_cell($n,'text','','#'.$name,array(
						'size' => intval($shown > 0 ? $shown : $max).','.intval($max)
					));
				}
				else	// header-row
				{
					$label['span'] = 'all';
					$tpl->new_cell($n);		// is needed even if its over-span-ed
					$row_class = 'th';
				}
				if (!empty($field['help']) && $row_class != 'th')
				{
					$input['help'] = $field['help'];
					$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
				}
				$tpl->set_row_attributes($n,0,$row_class);
			}
			// create an empty line which (should) take all the remaining height
			$tpl->new_cell(++$n,'label','','',array(
				'span' => 'all'
			));
			$tpl->set_row_attributes($n,'99%','row');

			// set width of 1. (label) column to 100
			$tpl->set_column_attributes(0,'100');

			$tpl->set_rows_cols();		// msie (at least 5.5 shows nothing with div overflow=auto)
			$tpl->size = '100%,100%'.($tpl->html->user_agent != 'msie' ? ',,,,,auto' : '');
			//echo '<pre style="text-align: left;">'; print_r($tpl); echo "</pre>\n";

			if (count($tpl->data) < 2)
			{
				$cell['type'] = 'label';
				return True;
			}
			$cell['size'] = '';	// no separate namespace
			$cell['type'] = 'template';
			$cell['name'] = $tpl->name;
			$cell['obj'] = &$tpl;

			return True;	// extra Label is ok
		}
	}
