<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog Links                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if(!isset($GLOBALS['phpgw_info']['flags']['included_classes']['bolink']))
	{
		include(PHPGW_API_INC . '/../../infolog/inc/class.bolink.inc.php');
		$GLOBALS['phpgw_info']['flags']['included_classes']['bolink'] = True;
	}

	/*!
	@class uilink
	@author ralfbecker
	@abstract generalized linking between entries of phpGroupware apps - HTML UI layer
	@discussion This class is the UI to show/modify the links
	@discussion Links have to ends each pointing to an entry, an entry is a double:
	@discussion app   app-name or directory-name of an phpgw application, eg. 'infolog'
	@discussion id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
	*/
	class uilink extends bolink
	{
		function uilink( )
		{
			$this->bolink( );							// call constructor of derived class
			$this->public_functions += array(	// extend public_functions
				'getEntry' => True,
				'showLinks' => True
			);
		}

		/*!
		@function getEntry
		@syntax getEntry( $name )
		@author ralfbecker
		@abstract HTML UI to query user for one side of a link: an entry of a supported app
		@param $name base-name of the input-fields
		@result html: table-row(s) with 4 cols
		*/
		function getEntry($name,$app='',$id=0)
		{
			$value = get_var($name,array('POST'));
			if (!is_array($value))
			{
				$value = array();
			}
			if ($this->debug)
			{
				echo "<p>uilink.getEntry('$name','$app',$id): $name = "; _debug_array($value);
			}
			if ($value['create'] && $value['app'] && $value['id'] && $app)
			{
				$this->link($app,&$id,$value['app'],$value['id'],$value['remark']);
			}
			if ($value['search'] && count($ids = $this->query($value['app'],$value['query'])))
			{
				$value = array(
					'app' => $value['app'],
					'options-id' => $ids,
					'remark' => ''
				);
				$etemplate = CreateObject('etemplate.etemplate','infolog.linkto_widget.create');
				$html = CreateObject('infolog.html');
				$out = $etemplate->show($value,'','',$name)."\n".$html->input_hidden($name.'[app]',$value['app']);
			}
			else
			{
				$value = array(
					'app' => $value['app'],
					'options-app' => $this->app_list(),
					'query' => '',
					'msg' => $value['search'] ? 'Nothing found - try again!!!' : ''
				);
				$etemplate = CreateObject('etemplate.etemplate','infolog.linkto_widget.search');
				$out = $etemplate->show($value,'','',$name);
			}
			$out = str_replace('[]','',$out);
			return eregi_replace('[</]*table[^>]*>','',$out);
		}

		/*!
		@function showLinks
		@syntax showLinks( $name,$app,$id,$only_app='',$show_unlink=True )
		@author ralfbecker
		@abstract HTML UI to show & delete existing links to $app,$id
		@param $name base-name of the input-fields
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
		@param $show_unlink boolean show unlink button for each link (default true)
		@result html: table-row(s) with 4 cols
		*/
		function showLinks($name,$app,$id,$only_app='',$show_unlink=True)
		{
			$value = get_var($name,array('POST'));
			if (!is_array($value))
			{
				$value = array();
			}
			list($unlink) = @each($value['unlink']);
			if ($this->debug)
			{
				echo "<p>uilink.showLinks: app='$app',id='$id', unlink=$unlink, $name = "; _debug_array($value);
			}
			if ($unlink)
			{
				$this->unlink($unlink,$app,$id);
				//echo "<p>$unlink unlinked</p>\n";
			}
			$etemplate = CreateObject('etemplate.etemplate','infolog.linklist_widget');
			$links = $this->get_links($app,$id,$only_app);
			$value = array();
			for($row=$etemplate->rows-1; list(,$link) = each($links); ++$row)
			{
				$value[$row] = $link;
				$value[$row]['title'] = $this->title($link['app'],$link['id']);
			}
			$value['app']   = $app;
			$value['id']    = $id;
			$value['title'] = $this->title($app,$id);

			$out = $etemplate->show($value,'','',$name);

			$out = str_replace('[]','',$out);
			return eregi_replace('[</]*table[^>]*>','',$out);
		}

		/*!
		@function viewLink
		@syntax viewLink( $app,$id,$content='' )
		@author ralfbecker
		@abstract link to view entry $id of $app
		@param $content if set result will be like "<a href=[link]>$content</a>"
		@result link to view $id in $app or False if no link for $app registered or $id==''
		*/
		function viewLink($app,$id,$html='')
		{
			$view = $this->view($app,$id);
			if (!count($view))
			{
				return False;
			}
			$html = CreateObject('infolog.html');
			return $content == '' ? $html->link('/index.php',$view) : $html->a_href($content,'/index.php',$view);
		}

		/*!
		@function linkBox
		@syntax linkBox( $app,$id,$only_app='',$show_unlink=True )
		@author ralfbecker
		@abstract HTML UI to show, delete & add links to $app,$id
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
		@param $show_unlink boolean show unlink button for each link (default true)
		@result html: table-row(s) with 4 cols
		*/
		function linkBox($app,$id,$only_app='',$show_unlink=True)
		{

		}
	}




