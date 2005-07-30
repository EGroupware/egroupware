<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.egroupware.org                                                *
	* Written by RalfBecker@outdoor-training.de to emulate the old preferences *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info']['flags'] = array(
		'noheader'                => True,
		'noappheader'             => True,
		'nonavbar'                => True,
		'currentapp'              => 'preferences',
		'enable_nextmatchs_class' => True,
	);
	include('../header.inc.php');

	function create_section($title)
	{
		$GLOBALS['settings'][] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'title'  => $title,
			'type'   => 'section',
		);
	}

	function create_input_box($label,$name,$help='',$default='',$size='',$maxsize='',$type='',$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'input',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}

	function create_password_box($label,$name,$help='',$size='',$maxsize='',$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'password',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}

	function create_text_area($label,$name,$rows,$cols,$help='',$default='',$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'text',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}

	function create_select_box($label,$name,$values,$help='',$default='',$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'select',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}

	function create_check_box($label,$name,$help='',$default='',$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'check',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}

	function create_notify($label,$name,$rows,$cols,$help='',$default='',$values='',$subst_help=True,$run_lang=True)
	{
		$GLOBALS['settings'][$name] = array(
			'admin'  => True,	// admin is controlled by the old-format hook_settings file itself
			'xmlrpc' => True,	// make everything availible via xmlrpc
			'type'   => 'notify',
		);
		foreach(array('label','name','help','default','size','maxsize','type','run_lang','rows','cols','values','subst_help') as $var)
		{
			if(isset($$var))
			{
				$GLOBALS['settings'][$name][$var] = $$var;
			}
		}
	}
	ExecMethod('preferences.uisettings.index');
