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

	/* $Id$ */
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
<ul><li><b>項目追加:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
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
<b>備考:</b> 他のアプリケーションで備えているアクセス権（プライベート、グループ、グローバル）も、このアプリケーションで備えています。</li></ul><p/></font>
<?php $phpgw->common->phpgw_footer(); ?>
