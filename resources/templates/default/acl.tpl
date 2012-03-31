<center>
  <table border="0" cellspacing="2" cellpadding="2" width="100%">
   <tr>
    <td colspan="5" align="center" bgcolor="#c9c9c9"><b>{title}<b/></td>
   </tr>
   <tr>
    <td colspan="5" align="left">
     <table border="0" width="100%">
      <tr>
	   {left}
       <td align="center">{lang_showing}</td>
	   {right}
      </tr>
     </table>
    </td>
   </tr>
   <tr>
    <td>&nbsp;</td>
    <td colspan="5" align="right">
	<form method="POST"><input type="text" name="query"  value="{query}" />&nbsp;<input type="submit" name="btnSearch" value="{lang_search}" /></form>
	</td>
  </tr>
 </table>
 <form method="POST">
  <table border="0" cellspacing="2" cellpadding="2" width="100%">
   <tr bgcolor="{th_bg}" valign="middle" align="center">
	<td>{sort_cat}<br>{lang_cat_admin}</td>
	<td>{lang_read}</td>
	<td>{lang_write}<br>({lang_implies_read})</td>
	<td>{lang_calread}</td>
	<td>{lang_calbook}<br>({lang_implies_book})</td>
   </tr>
   <!-- BEGIN cat_list -->
   <tr bgcolor="{tr_color}">
	<td>
		&nbsp;{catname}<input type="hidden" name="catids[]" value="{catid}" /><br>
		<select name="inputadmin[{catid}][]">{admin}</select><br>
		<label><input type="checkbox" value="{catid}" name="location_cats[]" {location_checked} /> {lang_locations_rooms}</label>
	</td>
	<td align="center"><select multiple="multiple" size="5" name="inputread[{catid}][]">{read}</select></td>
	<td align="center"><select multiple="multiple" size="5" name="inputwrite[{catid}][]">{write}</select></td>
	<td align="center"><select multiple="multiple" size="5" name="inputcalread[{catid}][]">{calread}</select></td>
	<td align="center"><select multiple="multiple" size="5" name="inputcalbook[{catid}][]">{calbook}</select></td>
   </tr>
   <!-- END cat_list -->
   <tr>
    <td colspan="5" align="left">
	<br>
	<input type="submit" name="btnSave" value="{lang_save}"> &nbsp;
	<input type="submit" name="btnDone" value="{lang_done}">
    </td>
   </tr>
  </table>
 </form>
</center>
