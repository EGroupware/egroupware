<table border="0" cellspacing="0" 
cellpadding="0">
<?php
//Liste des classes thérapeutiques
$nomchamp = $_SESSION['activelangue'] . 
"_dos";
$sql = "select * from dossiers_dos 
where idparent_dos = " . FOLDER_ROOT . " order by " . $nomchamp;
$rs_dos = 
$utilisateur->parent->phptoolbox->DataAccess->Execute($sql);
//Il n'y a pas de classes thérapeutiques
if ($rs_dos->EOF) { ?>
	<tr>
		<td class="sstitre"><?php print 
		$_SESSION['INFOSCOMPTE']['CLASSESTH_EMPTY']; ?></td>
		<td class="cell">&nbsp;</td>
		<td>&nbsp;</td>
		</tr>
		<?php
}
else {
	$tab_usr_dos = 
		$utilisateur->gettabclassessouscrites($currentuser->id_usr);
	//Affiche les classes thérapeutiques
	while ($row_dos = $rs_dos->FetchRow()) { ?>
		<tr>
			<td class="sstitre"><?php print 
			$row_dos[$nomchamp]; ?></td>
			<td class="cell"><img 
			src="images/spaceur.gif" width="15" height="1"></td>
			<td><input name="coches_dos[]" 
			type="checkbox" disabled="true" value="<?php print $row_dos['id_dos']; 
			?>"<?php if (in_array($row_dos['id_dos'],$tab_usr_dos)) print ' 
			checked'; ?>>
			</td>
			</tr>
			<?php }
} ?>
</table>
