<?php
	/**************************************************************************\\
	* eGroupWare - Setup                                                     *
	* http://www.eGroupWare.org                                                *
	* Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\\**************************************************************************/

	/* $Id$ */

	$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
	$file = Array(
		'eTemplate Editor' => $GLOBALS['egw']->link('/index.php','menuaction=etemplate.editor.edit'),
		'DB-Tools' => $GLOBALS['egw']->link('/index.php','menuaction=etemplate.db_tools.edit'),
	);
	if (@$GLOBALS['egw_info']['user']['apps']['developer_tools'])
	{
		$file += array(
			'_NewLine_', // give a newline
			'developer_tools' => $GLOBALS['egw']->link('/index.php','menuaction=developer_tools.uilangfile.index'),
		);
	}
	display_sidebox($appname,$menu_title,$file);

	$menu_title = lang('Documentation');
	$docs = $GLOBALS['egw_info']['server']['webserver_url'].'/etemplate/doc/';
	$file = Array(
		array(
			'text'   => 'eTemplate Tutorial',
			'link'   => $docs.'etemplate.html',
			'target' => 'docs'
		),
		array(
			'text'   => 'eTemplate Reference',
			'link'   => $docs.'reference.html',
			'target' => 'docs'
		),
		array(
			'text'   => 'eGroupWare '.lang('Documentation'),
			'no_lang' => True,
			'link'   => 'http://egroupware.org/wiki/DeveloperDocs',
			'target' => 'docs'
		),
		array(
			'text'   => 'CSS properties',
			'link'   => 'http://www.w3.org/TR/REC-CSS2/propidx.html',
			'target' => 'docs'
		),

	);
	display_sidebox($appname,$menu_title,$file);
