<b>{lang_changepassword}</b><hr><p>

   <center>{messages}</center>

   <form method="POST" action="{form_action}">
    <table border="0">
     <tr>
       <td>
        {lang_enter_password}
       </td>
       <td>
        <input type="password" name="n_passwd">
       </td>
     </tr>
     <tr>
       <td>
        {lang_reenter_password}
       </td>
       <td>
        <input type="password" name="n_passwd_2">
       </td>
     </tr>
     <tr>
       <td colspan="2">
        <input type="submit" name="submit" value="{lang_change}">
       </td>
     </tr>
    </table>
   </form>
   <br>
   <pre>{sql_message}</pre>