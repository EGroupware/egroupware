<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'about';
	include('header.inc.php');

	$app = $HTTP_GET_VARS['app'];

	$GLOBALS['phpgw']->xslttpl->add_file(array('about'));

	if ($app == 'phpGroupWare' || $app == 'about')
	{
		$app = 'phpgwapi';
	}
	$app_data['title'] = str_replace('- ','-',ucwords(str_replace('_','- ',$app)));
	$app_data['icon'] = $GLOBALS['phpgw']->common->image($app,'navbar');
	
	$setup_file = PHPGW_INCLUDE_ROOT . '/' . $app . '/setup/setup.inc.php';

	if (@file_exists($setup_file))
	{
		include($setup_file);
		
		$fields = array(
			'version',
			'description',
			'note',
			'author',
			'maintainer',
			'maintainer_email',
			'license',
			'based_on',
			'based_on_url'
		);
		while (list(,$field) = each($fields))
		{
			$app_data[$field] = $setup_info[$app][$field];
		}
		$app_data['maintainer_email'] = ereg_replace('([-_a-zA-Z0-9.]+@[-_a-zA-Z0-9.]+)',
			'<a href="mailto:\\1">\\1</a>',$app_data['maintainer_email']);
	}
	$data = array
	(
		'phpgw_logo'			=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi'),
		'lang_url_statustext'	=> lang('phpGroupWare homepage'),
		'lang_version'			=> lang('version'),
		'phpgw_version'			=> 'phpGroupWare API ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],
		'phpgw_descr'			=> lang('is a multi-user, web-based groupware suite written in PHP'), 
		'about_app'				=> $app_data + array(
			'lang_version'    => lang('version'),
			'lang_written_by' => lang('written by'),
			'lang_based_on'   => lang('based on'),
			'lang_maintainer' => lang('maintainer'),
			'lang_license'    => lang('license')
		)
	);

	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('about_data' => $data));
?>
