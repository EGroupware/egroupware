<?php
  /**************************************************************************\
  * phpGroupWare - User manual                                               *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id: edit_delete.php,v 1.1 2001/05/11 02:50:44 skeeter Exp $ */

	$phpgw_flags = Array(
		'currentapp'	=> 'manual'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
日、週、月単位での検索可能なカレンダー・スケジュールアプリケーションです。優先度の高いイベントの通知機能も備えています。<br/>
<ul><li><b>訂正:削除</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
予定を訂正するために、このアイコンをクリックします。
訂正する予定が表示され、訂正か削除を選択するボタンが表示されます。<br/>
<b>備考:</b>訂正や削除は、自分で作成したものに限ります。</li><p/></ul></font>
