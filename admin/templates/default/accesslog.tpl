<!-- BEGIN header -->
  <p>
  <table border="0" align="center" width="75%">
   <tr>
    <td bgcolor="{th_bg}" align="center" colspan="5">
      {lang_last_x_logins}
    </td>
   </tr>
   <tr bgcolor="{th_bg}">
    <td>{lang_loginid}</td>
    <td>{lang_ip}</td>
    <td>{lang_login}</td>
    <td>{lang_logout}</td>
    <td>{lang_total}</td>
   </tr>
<!-- END header -->

{output}

<!-- BEGIN row -->
   <tr bgcolor="{tr_color}">
    <td>{row_loginid}</td>
    <td>{row_ip}</td>
    <td>{row_li}</td>
    <td>{row_lo}</td>
    <td>{row_total}</td>
   </tr>
<!-- END row -->

<!-- BEGIN footer -->
   <tr bgcolor="{bg_color}">
    <td colspan=5 align=left>{footer_total}</td>
   </tr>

   <tr bgcolor="{bg_color}">
    <td colspan=5 align=left>{lang_percent}</td>
   </tr>
  </table>
<!-- END footer -->
