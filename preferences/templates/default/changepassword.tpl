<br>

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
        <table cellspacing="5"><tr>
         <td><input type="submit" name="change" value="{lang_change}"></td>
         <td><input type="submit" name="cancel" value="{lang_cancel}"></td>
        </tr></table>
       </td>
     </tr>
    </table>
   </form>
   <br>
   <pre>{sql_message}</pre>
