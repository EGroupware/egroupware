<?php
   /**************************************************************************\
   * eGroupWare - Home - Preferences                                          *
   * http://egroupware.org                                                    *
   * Written by Edo van Bruggen <edovanbruggen@raketnet.nl>                   *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; version 2 of the License.                     *
   \**************************************************************************/
  
	/* $Id$ */

	$prev_img = Array(
		'no' => lang('Never'),
		'only_tn' => lang('Only if thumnails exits'),
		'yes' => lang('Yes')
	);

	$max_prev=array(
		'1'  => '1',
		'2'  => '2',
		'3'  => '3',
		'4'  => '4',
		'5'  => '5',
		'10' => '10',
		'20' => '20',
		'30' => '30',
		'-1' => lang('No max. number')
	);

	/* Settings array for this app */
	$GLOBALS['settings'] = array(
		'prefssection' => array(
			'type'  => 'section',
			'title' => 'Home',
			'xmlrpc' => False,
			'admin'  => False
		),
		'prev_img' => array(
			'type'   => 'select',
			'label'  => 'Preview thumbs or images in form',
			'name'   => 'prev_img',
			'values' => $prev_img,
			'help'   => "When you choose 'Never', only links to the images are displayed; when you choose 'Only if thumnails exists' previews are  shown if an thumbnail of the image exists; if you choose 'Yes' all images are shown",
			'xmlrpc' => False,
			'admin'  => False
		)
	);
?> 
