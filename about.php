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

	$setup_file = PHPGW_INCLUDE_ROOT . '/' . $app . '/setup/setup.inc.php';

	if (@file_exists($setup_file))
	{
		include($setup_file);
		
		$fields = array
		(
			'version',
			'description',
			'note',
			'author',
			'license',
			'based_on',
			'maintainer'
		);
		while (list(,$field) = each($fields))
		{
			$app_data[$field] = $setup_info[$app][$field];

			if ($field == 'description')
			{
				$app_data[$field] = lang($setup_info[$app][$field]);
			}
		}

		if (isset($app_data['note']) && !empty($app_data['note']))
		{
			$app_data['note'] = lang($app_data['note']);
		}
	}

	$data = array
	(
		'phpgw_logo'			=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi'),
		'lang_url_statustext'	=> lang('phpGroupWare --> homepage'),
		'lang_version'			=> lang('version'),
		'phpgw_version'			=> 'phpGroupWare API ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],
		'phpgw_descr'			=> lang('is a multi-user, web-based groupware suite written in PHP'), 
		'about_app'				=> $app_data + array
		(
			'title'				=> lang($app),
			'icon'				=> $GLOBALS['phpgw']->common->image($app,'navbar'),
			'lang_version'		=> lang('version'),
			'lang_author'		=> lang('author'),
			'lang_based_on'		=> lang('based on'),
			'lang_maintainer'	=> lang('maintainer'),
			'lang_license'		=> lang('license')
		)
	);

	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('about_data' => $data));
?>
