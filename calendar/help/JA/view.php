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

	/* $Id: view.php,v 1.2 2001/05/11 03:00:00 skeeter Exp $ */

	$phpgw_flags = Array(
		'currentapp'	=> 'manual'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
日、週、月単位での検索可能なカレンダー・スケジュールアプリケーションです。優先度の高いイベントの通知機能も備えています。<br/>
左上にあるアイコンをクリックすると、今日（時間単位）、今週および今月の予定を表示することができます。<br/>
<ul><li><b>表示:</b><img src="<?php echo $phpgw->common->image('calendar','today.gif'); ?>">今日 <img src="<?php echo $phpgw->common->image('calendar','week.gif'); ?>">今週 <img src="<?php echo $phpgw->common->image('calendar','month.gif'); ?>">今月 <img src="<?php echo $phpgw->common->image('calendar','year.gif'); ?>">今年<br/>
<i>今日:</i><br/>
一日の予定を時間単位に区切って表示します。開始時間と終了時間は、ユーザ設定にて設定します。<br/>
<i>今週:</i><br/>
週単位で予定を表示します。週の初めの曜日は、ユーザ設定にて設定します。<br/>
<i>今月:</i><br/>
月単位で予定を表示します。月の表示はデフォルト設定となっています。先月や翌月にワンクリックでアクセスすることができます。<br/>
<i>今年:</i><br/>
年単位で予定を表示します。小さい月単位のカレンダーを一年分表示します。<p/></li></ul></font>
