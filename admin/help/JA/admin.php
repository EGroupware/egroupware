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
<li><b>アカウント管理:</b><br/>
<i>ユーザアカウント:</i><br/>
ユーザアカウントを追加、訂正、削除することができます。グループに属するメンバー設
定や、アプリケーションのアクセス権の設定も可能です。<br/>
<i>ユーザグループ:</i><br/>
ユーザが所属するグループを追加、訂正削除することができます。</li><p/>

<li><b>セッション管理:</b><br/>
<i>セッション参照:</i><br/>
現在のセッションの、IPアドレスやログイン時間、アイドル時間などを表示します。セッションを切断することも可能です。<br/>
<i>アクセスログ参照:</i><br/>
phpGroupWareへのアクセスログを表示します。ログインID,IPアドレス,ログイン時間,ログアウト時間,利用時間を表示します。</li><p/>

<li><b>Headline sites:</b><br/>
Administer headline sites as seen by users in the headlines application.<br/>
<i>Edit:</i> Options for the headline sites:<br/>
Display,BaseURL, NewsFile,Minutes between reloads,Listing Displayed,News Type.<br/>
<i>Delete:</i>Remove an existing headling site, clicking on delete will give
you a checking page to be sure you do want to delete.<br/>
<i>View:</i>Displays set options as in edit.<br/>
<i>Add:</i>Form for adding new headline site, options as in edit.</li><p/>

<li><b>ネットニュース:</b><br/>
ニュースグループの購読設定をします。</li><p/>
<li><b>サーバ情報:</b><br/>
サーバで動作している PHP の情報を、phpinfo() で表示します。</li><p/>
</ul></font>
