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
		'currentapp'	=> 'manual',
		'admin_header'	=> True,
		'enable_utilities_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$appname = 'admin';
?>
<img src="<?php echo $phpgw->common->image($appname,'navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2">
<p>
この機能は、通常このシステムのシステム管理者のみ利用可能です。
システム管理者は、すべてのアプリケーション、ユーザとグループのアカウント、セッションログを操作します。
<ul>
<li><b>セッション管理:</b>
<p><i>セッション参照:</i>
<br>現在のセッションの、IPアドレスやログイン時間、アイドル時間などを表示します。セッションを切断することも可能です。
<p><i>アクセスログ参照:</i>
<br>phpGroupWareへのアクセスログを表示します。ログインID,IPアドレス,ログイン時間,ログアウト時間,利用時間を表示します。
</ul>
<?php $phpgw->common->phpgw_footer(); ?>
