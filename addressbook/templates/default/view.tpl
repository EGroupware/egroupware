<!-- BEGIN view_header -->
<p>&nbsp;<b>{lang_viewpref}</b><hr><p>
<table border="0" cellspacing="2" cellpadding="2" width="70%" align="center">
<!-- END view_header -->
<!-- BEGIN view_row -->
  <tr bgcolor="{th_bg}">
    <td align="right" width="30%"><b>{display_col}</b>:</td><td width="70%">{ref_data}</td>
  </tr>
<!-- END view_row -->
{cols}
<!-- BEGIN view_footer -->
  <tr>
    <td colspan="4">&nbsp;</td>
  </tr>
  <tr>
    <td><b>{lang_owner}</b></td>
    <td>{owner}</td>
  </tr>
  <tr>
    <td><b>{lang_access}</b></td>
    <td>{access}</b>
    </td>
  </tr>
  <tr>
    <td><b>{lang_category}</b></td>
    <td>{catname}</b></td>
  </tr>
  </td>
</td>
</table>
<!-- END view_footer -->
<!-- BEGIN view_buttons -->
<center>
 <TABLE border="0" cellpadding="1" cellspacing="1">
  <TR> 
   <TD>
     {edit_button}
   </TD>
   <TD>
     {copy_button}
   </TD>
   <TD>
     {vcard_button}
   </TD>
   <TD>
     {done_button}
   </TD>
  </TR>
 </TABLE>
</center>
<!-- END view_buttons -->
