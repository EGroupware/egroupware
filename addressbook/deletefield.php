<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * (http://www.phpgroupware.org)                                            *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	if ($HTTP_POST_VARS['confirm'])
	{
		$GLOBALS['phpgw_info']['flags'] = array(
			'noheader' => True, 
			'nonavbar' => True
		);
	}

	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'addressbook';
	include('../header.inc.php');

	if (!$HTTP_POST_VARS['field'])
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/addressbook/fields.php'));
	}

	$field = $HTTP_POST_VARS['field'];
	$field_id = $HTTP_POST_VARS['field_id'] ? $HTTP_POST_VARS['field_id'] : $HTTP_GET_VARS['field_id'];
	$start = $HTTP_POST_VARS['start'];
	$query = $HTTP_POST_VARS['query'];
	$sort = $HTTP_POST_VARS['sort'];

	if ($HTTP_POST_VARS['confirm'])
	{
		save_custom_field($field);
		Header('Location: ' . $GLOBALS['phpgw']->link('/addressbook/fields.php',"start=$start&query=$query&sort=$sort"));
	}
	else
	{
		$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
			. '<input type="hidden" name="order" value="' . $order .'">' . "\n"
			. '<input type="hidden" name="query" value="' . $query .'">' . "\n"
			. '<input type="hidden" name="start" value="' . $start .'">' . "\n"
			. '<input type="hidden" name="field" value="' . $field .'">' . "\n";

		$GLOBALS['phpgw']->template->set_file(array('field_delete' => 'delete_common.tpl'));
		$GLOBALS['phpgw']->template->set_var('messages',lang('Are you sure you want to delete this field?'));

		$nolinkf = $GLOBALS['phpgw']->link('/addressbook/fields.php',"field_id=$field_id&start=$start&query=$query&sort=$sort");
		$nolink = '<a href="' . $nolinkf . '">' . lang('No') . '</a>';
		$phpgw->template->set_var('no',$nolink);

		$yeslinkf = $GLOBALS['phpgw']->link('/addressbook/deletefield.php','field_id=' . $field_id . '&confirm=True');
		$yeslinkf = '<form method="POST" name="yesbutton" action="' . $GLOBALS['phpgw']->link('/addressbook/deletefield.php') . '\">'
			. $hidden_vars
			. '<input type="hidden" name="field_id"  value="' . $field_id . '">'
			. '<input type="hidden" name="confirm"   value="True">'
			. '<input type="submit" name="yesbutton" value="Yes">'
			. '</form><script>document.yesbutton.yesbutton.focus()</script>';

		$yeslink = '<a href="' . $yeslinkf . '">' . lang('Yes') . '</a>';
		$yeslink = $yeslinkf;
		$GLOBALS['phpgw']->template->set_var('yes',$yeslink);

		$GLOBALS['phpgw']->template->pparse('out','field_delete');
	}

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
