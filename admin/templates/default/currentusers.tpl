<!-- BEGIN header -->
<center>
{lang_current_users}:
<table border="0" width="50%">
 <tr bgcolor="{bg_color}">
   {left_next_matchs}
   <td>&nbsp;</td>
   {right_next_matchs}
 </tr> 

 <tr bgcolor="{th_bg}">
  <td>{sort_loginid}</td>
  <td>{sort_ip}</td>
  <td>{sort_login_time}</td>
  <td>{sort_idle}</td>
  <td>{lang_kill}</td>
 </tr>
<!-- END header -->

{output}

<!-- BEGIN row -->
 <tr bgcolor="{tr_color}">
  <td>{row_loginid}</td>
  <td>{row_ip}</td>
  <td>{row_logintime}</td>
  <td>{row_idle}</td>
  <td>{row_kill}</td>
 </tr>
<!-- END row -->

<!-- BEGIN footer -->
</center>
</table>
<!-- END footer -->
