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
<img src="<?php echo $phpgw->common->image('addressbook','navbar.gif'); ?>" border="0">
<font face="<?php echo $font ?>" size="2"><p/>
ビジネスパートナーや友人などの連絡先情報を保管するためのアドレス帳です。
<ul><li><b>追加	:</b><br/>
追加ボタンをクリックすると、次の項目を入力することができます。
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
名:<br/>
電子メール:<br/>
自宅電話番号:<br/>
会社電話番号:<br/>
携帯電話:<br/>
町域:<br/>
市区町村:<br/>
都道府県:<br/>
郵便番号:<br/>
アクセス権:<br/>
グループ設定:<br/>
ノート:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
姓:<br/>
会社名:<br/>
FAX:<br/>
ページャー番号:<br/>
その他の番号:<br/>
誕生日:</td></table>
などの
各項目を入力したら、OKボタンをクリックします。</li><p/></ul>
プライベートデータにアクセスするには、利用許可（ユーザ設定）を設定する必要があります。
ユーザ設定ではあなたが作成したアドレス帳を他のユーザが、表示・訂正・削除することができるアクセス権を設定することができます。
<p/>
<?php $phpgw->common->phpgw_footer(); ?>

