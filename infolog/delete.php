<?php
	/**************************************************************************\
	* phpGroupWare - Info Log                                                 *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'nofooter'   => True,
		'currentapp' => 'infolog'
	);

	include('../header.inc.php');
	$phpgw->infolog = CreateObject('infolog.infolog');
	$html = $phpgw->infolog->html;

	$hidden_vars = array( 'sort' => $sort,'order' => $order,'query' => $query,'start' => $start,
								 'filter' => $filter,'cat_id' => $cat_id );
	if (! $info_id) {
		Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars));
	}

	if (! $phpgw->infolog->check_access($info_id,PHPGW_ACL_DELETE)) {
		Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars));
	}
	if ($confirm) {
		$phpgw->infolog->delete($info_id);

		Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars + array( 'cd' => 16 )));
	} else {
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
		$t->set_file(array( 'info_delete' => 'delete.tpl' ));
		$t->set_var( $phpgw->infolog->setStyleSheet( ));
		$t->set_var( $phpgw->infolog->infoHeaders(  ));
		$t->set_var( $phpgw->infolog->formatInfo( $info_id ));
		$t->set_var('lang_info_action',lang('Info Log - Delete'));

		$t->set_var('deleteheader',lang('Are you sure you want to delete this entry'));
		$t->set_var('no_button',$html->form_1button('no_button','No - Cancel','','/infolog/index.php',$hidden_vars));
		$t->set_var('yes_button',$html->form_1button('yes_button','Yes - Delete','','/infolog/delete.php',
																	$hidden_vars + array('info_id' => $info_id,'confirm' => 'True')));
		$t->pfp('out','info_delete');

		$phpgw->common->phpgw_footer();
		echo parse_navbar_end();
	}
?>