<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates - Buiseness Objects                     *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include(PHPGW_API_INC . '/../../etemplate/inc/class.soetemplate.inc.php');

	/*!
	@class boetemplate
	@abstract Buiseness Objects for eTemplates
	@discussion Not so much so far, as the most logic is still in the UI-class
	@param $types,$alings converts internal names/values to (more) human readible ones
	*/
	class boetemplate extends soetemplate
	{
		var $types = array(
			'label'	=> 'Label',	// Label $cell['label'] is (to be translated) textual content
			'text'	=> 'Text',	// Textfield 1 Line (size = [length][,maxlength])
			'textarea' => 'Textarea',	// Multiline Text Input (size = [rows][,cols])
			'checkbox'=> 'Checkbox',
			'radio'	=> 'Radiobutton',	// Radiobutton (size = value if checked)
			'button'	=> 'Submitbutton',
			'hrule'	=> 'Horizontal Rule',
			'template' => 'Template',	// $cell['name'] contains template-name, $cell['size'] index into $content,$cname,$readonlys
			'image' => 'Image',			// label = url, name=link or method, help=alt or title
			'date'	=> 'Date', 			// Datefield, size='' timestamp or size=format like 'm/d/Y'
			'select'	=>	'Selectbox',	// Selectbox ($sel_options[$name] or $content[options-$name] is array with options)
			// if size > 1 then multiple selections, size lines showed
			'select-percent' => 'Select Percentage',
			'select-priority' => 'Select Priority',
			'select-access' => 'Select Access',
			'select-country' => 'Select Country',
			'select-state' => 'Select State',	// US-states
			'select-cat' => 'Select Cathegory', // Cathegory-Selection, size: -1=Single+All, 0=Single, >0=Multiple with size lines
			'select-account' => 'Select Account',	// label=accounts(default),groups,both
			// size: -1=Single+not assigned, 0=Single, >0=Multiple
			'raw'		=> 'Raw',	// Raw html in $content[$cell['name']]
		);
		var $aligns = array(
			''       => 'Left',
			'right'  => 'Right',
			'center' => 'Center'
		);

		/*!
		@function boetemplate
		@abstract constructor of class
		@discussion Calls the constructor of soetemplate
		*/
		function boetemplate()
		{
			$this->soetemplate();
		}

		/*!
		@function expand_name($name,$c,$row,$c_='',$row_='',$cont=array())
		@abstract allows a few variables (eg. row-number) to be used in field-names
		@discussion This is mainly used for autorepeat, but other use is possible.
		@discussion You need to be aware of the rules PHP uses to expand vars in strings, a name
		@discussion of "Row$row[length]" will expand to 'Row' as $row is scalar, you need to use
		@discussion "Row${row}[length]" instead. Only one indirection is allowd in a string by php !!!
		@discussion Out of that reason we have now the variable $row_cont, which is $cont[$row] too.
		@discussion Attention !!!
		@discussion Using only number as index in field-names causes a lot trouble, as depending
		@discussion on the variable type (which php determines itself) you used filling and later
		@discussion accessing the array it can by the index or the key of an array element.
		@discussion To make it short and clear, use "Row$row" or "$col$row" not "$row" or "$row$col" !!!
		@param $name the name to expand
		@param $c is the column index starting with 0 (if you have row-headers, data-cells start at 1)
		@param $row is the row number starting with 0 (if you have col-headers, data-cells start at 1)
		@param $c_, $row_ are the respective values of the previous template-inclusion,
		@param            eg. the column-headers in the eTemplate-editor are templates itself,
		@param            to show the column-name in the header you can not use $col as it will
		@param            be constant as it is always the same col in the header-template,
		@param            what you want is the value of the previous template-inclusion.
		@param $cont content array of the template, you might use it to generate button-names with
		@param       id values in it: "del[$cont[id]]" expands to "del[123]" if $cont = array('id' => 123)
		*/
		function expand_name($name,$c,$row,$c_='',$row_='',$cont='')
		{
			if(!$cont)
			{
				$cont = array();
			}
			$col = $this->num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
			$col_ = $this->num2chrs($c_-1);
			$row_cont = $cont[$row];

			eval('$name = "'.$name.'";');

			return $name;
		}

		/*!
		@function autorepeat_idx
		@abstract Checks if we have an row- or column autorepeat and sets the indexes for $content, etc.
		@discussion Autorepeat is important to allow a variable numer of rows or cols, eg. for a list.
		@discussion The eTemplate has only one (have to be the last) row or column, which gets
		@discussion automaticaly repeated as long as content is availible. To check this the content
		@discussion has to be in an sub-array of content. The index / subscript into content is
		@discussion determined by the content of size for templates or name for regular fields.
		@discussion An autorepeat is defined by an index which contains variables to expand.
		@discussion (vor variable expansion in names see expand_names). Usually I use the keys
		@discussion $row: 0, 1, 2, 3, ... for only rows, $col: '@', 'A', 'B', 'C', ... for only cols or
		@discussion $col$row: '@0','A0',... '@1','A1','B1',... '@2','A2','B2',... for both rows and cells.
		@discussion In general everything expand_names can generate is ok - see there.
		@discussion As you usually have col- and row-headers, data-cells start with '1' or 'A' !!!
		@param $cell array with data of cell: name, type, size, ...
		@param $c,$r col/row index starting from 0
		@param &$idx returns the index in $content and $readonlys (NOT $sel_options !!!)
		@param &$idx_cname returns the basename for the form-name: is $idx if only one value
		@param       (no ',') is given in size (name (not template-fields) are always only one value)
		@param $check_col boolean to check for col- or row-autorepeat
		@returns true if cell is autorepeat (has index with vars / '$') or false otherwise
		*/
		function autorepeat_idx($cell,$c,$r,&$idx,&$idx_cname,$check_col=False)
		{
			$org_idx = $idx = $cell[ $cell['type'] == 'template' ? 'size' : 'name' ];

			$idx = $this->expand_name($idx,$c,$r);
			if (!($komma = strpos($idx,',')))
			{
				$idx_cname = $idx;
			}
			else
			{
				$idx_cname = substr($idx,1+$komma);
				$idx = substr($idx,0,$komma);
			}
			$Ok = False;
			$pat = $org_idx;
			while (!$Ok && ($pat = strstr($pat,'$')))
			{
				$pat = substr($pat,$pat[1] == '{' ? 2 : 1);

				if ($check_col)
				{
					$Ok = $pat[0] == 'c' && !(substr($pat,0,4) == 'cont' ||
					substr($pat,0,2) == 'c_' || substr($pat,0,4) == 'col_');
				}
				else
				{
					$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' || substr($pat,0,4) == 'row_');
				}
			}
			if ($this->name == $this->debug)
			{
				echo "$this->name ".($check_col ? 'col' : 'row')."-check: c=$c, r=$r, idx='$org_idx' ==> ".($Ok?'True':'False')."<p>\n";
			}

			return $Ok;
		}
	}
