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

	/* $Id: calendar.php,v 1.1 2001/05/11 02:50:44 skeeter Exp $ */

	$phpgw_flags = Array(
		'currentapp'	=> 'manual'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$font = $phpgw_info['theme']['font'];
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $font; ?>" size="2"><p/>
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
年単位で予定を表示します。小さい月単位のカレンダーを一年分表示します。</li><p/>
<li><b>項目追加:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
自分自身やグループの他のメンバーのための新しい予定を追加するために、このアイコンをクリックします。
次の項目を入力するページが表示されます。
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
概要（タイトル）:<br/>
詳細:<br/>
日:<br/>
時間:<br/>
期間:<br/>
優先順位:<br/>
アクセス権:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
グループ:<br/>
参加者:<br/>
繰返しタイプ:<br/>
繰返し終了日:<br/>
頻度:</td></table>
などの項目を入力し、実行ボタンをクリックします。<br/>
<b>備考:</b> 他のアプリケーションで備えているアクセス権（プライベート、グループ、グローバル）も、このアプリケーションで備えています。</li><p/>
<li><b>訂正:削除</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
予定を訂正するために、このアイコンをクリックします。
訂正する予定が表示され、訂正か削除を選択するボタンが表示されます。<br/>
<b>備考:</b>訂正や削除は、自分で作成したものに限ります。</li><p/></ul></font>
