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
		'enable_utilities_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('preferences','navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>&nbsp;&nbsp;
Der Ort um &Auml;derungen an Ihrer persönlichen Groupware zu machen.<p/>&nbsp;&nbsp;
<ul>
<li><b>Ihr Passwort &Auml;ndern:</b><br/>
  Wird benutzt um Ihr login Passwort zum system zu &auml;ndern. Sie werden zwei 
  Eingabefelder angezeigt bekommen, geben Sie Ihr neues passwort in diese zwei 
  Felder ein und klicken Sie den &auml;ndern Knopf.</li>
<p/>&nbsp;&nbsp;
<li><b>W&auml;hlen Sie ein andere Farbschema:</b><br/>
  Hier &auml;ndern Sie Ihr Farbschema f&uuml;r das System. Es wird das aktuelle 
  Thema angezeigt (f&uuml;r neue benutzer ist das Default). W&auml;hlen Sie einfach 
  mit der Maus ein Thema ihrer Wahl aus. Das Aussehen wird sich nach dem Absenden 
  sofort ver&auml;ndern. 
  <p/>&nbsp;&nbsp;
<li><b>Ihr Profil &auml;ndern:</b><br/>
  Hier k&ouml;nnen Sie einige Details &uuml;ber sich selber, f&uuml;r die anderen 
  Benutzer des Systems eingeben. Titel, Telefonnummer, Kommentare und ein Bild 
  hoch laden.</li>
<p/>&nbsp;&nbsp;
<li><b>Newsgroups Lesen:</b><br/>
  Hier w&auml;hlen sie einfach welche Newsgruppen sie gerne abonnieren m&ouml;chten.</li>
<?php $phpgw->common->phpgw_footer(); ?>
