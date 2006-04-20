<?php
	/**
	 * eGroupWare - Setup 
	 * Originaly from the phpGW XSLT API
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

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
