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

	/* $Id: account.php,v 1.1 2001/05/24 12:00:00 itheart */
	
	$phpgw_flags = Array(
		'currentapp'	=> 'manual',
		'admin_header'	=> True,
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$appname = 'admin';
?>
<img src="<?php echo $phpgw->common->image($appname,'navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
この機能は、通常このシステムのシステム管理者のみ利用可能です。
システム管理者は、すべてのアプリケーション、ユーザとグループのアカウント、セッションログを操作します。
<ul>
<li><b>アカウント管理:</b><p/>
<i>ユーザアカウント:</i><br/>
ユーザアカウントを追加、訂正、削除することができます。グループに属するメンバー設定や、アプリケーションのアクセス権の設定も可能です。<p/>
<i>ユーザグループ:</i><br/>
ユーザが所属するグループを追加、訂正削除することができます。<p/>
</ul></font>
