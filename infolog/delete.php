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

	if (! $info_id) {
		Header('Location: ' . $phpgw->link('/infolog/index.php',"&sort=$sort&order=$order&query=$query&start=$start"
			. "&filter=$filter&cat_id=$cat_id"));
	}

	$phpgw->infolog = createobject('infolog.infolog');
	if (! $phpgw->infolog->check_access($info_id,PHPGW_ACL_DELETE)) {
		Header('Location: ' . $phpgw->link('/infolog/index.php',"&sort=$sort&order=$order&query=$query&start=$start"
			. "&filter=$filter&cat_id$cat_id"));
	}

	if ($confirm) {
		$phpgw->infolog->delete($info_id);

		Header('Location: ' . $phpgw->link('/infolog/index.php',"cd=16&sort=$sort&order=$order&query=$query&start="
			. "$start&filter=$filter&cat_id=$cat_id"));
	} else {
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$common_hidden_vars =
		  "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
		. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
		. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
		. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
		. "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n";

		$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL); 
		$t->set_file(array( 'info_delete' => 'delete.tpl' ));
		$t->set_var( $phpgw->infolog->setStyleSheet( ));
		$t->set_var( $phpgw->infolog->infoHeaders(  ));
		$t->set_var( $phpgw->infolog->formatInfo( $info_id ));
		$t->set_var('lang_info_action',lang('Info Log - Delete'));

		$t->set_var('deleteheader',lang('Are you sure you want to delete this entry'));

		$nolinkf = $phpgw->link('/infolog/index.php',"sort=$sort&order=$order&query=$query&start=$start&filter=$filter");
		$nolink = '<a href="' . $nolinkf . '">' . lang('No') .'</a>';
		
		$t->set_var('nolink',$nolink);
		$t->set_var('cancel_action',$nolinkf);
		$t->set_var('lang_cancel',lang('No - Cancel'));

		$yeslinkf = $phpgw->link('/infolog/delete.php',"info_id=$info_id&confirm=True&sort="
			. "$sort&order=$order&query=$query&start=$start&filter=$filter");
		
		$yeslink = '<a href="' . $yeslinkf . '">' . lang('Yes') . '</a>';

		$t->set_var('yeslink',$yeslink);
		$t->set_var('delete_action',$yeslinkf);
		$t->set_var('lang_delete',lang('Yes - Delete'));

		$t->pfp('out','info_delete');

		$phpgw->common->phpgw_footer();
		echo parse_navbar_end();
	}
?>