<!-- $Id$ -->
<!-- BEGIN form -->
<center>
<table border="0" width="80%" cellspacing="2" cellpadding="2">
<tr>
  <td colspan="1" align="center" bgcolor="#c9c9c9"><font face="{font}"><b>{title_categories}:&nbsp;{user_name}<b/></font></td>
</tr>
</table>
<font face="{font}">{message}</font>
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
  <form name="form" action="{actionurl}" method="POST">
    <tr>
     <td><font face="{font}">{lang_parent}:</font></td>
     <td><font face="{font}"><select name="cat_parent"><option value="">{lang_select_parent}</option>{category_list}</select></font></td>
    </tr>
    <tr>
     <td><font face="{font}">{lang_name}:</font></td>
     <td><font face="{font}"><input name="cat_name" size="50" value="{cat_name}"></font></td>
    </tr>
    <tr>
     <td><font face="{font}">{lang_descr}:</font></td>
     <td colspan=2><font face="{font}"><textarea name="cat_description" rows="4" cols="50" wrap="virtual">{cat_description}</textarea></font></td>
    </tr>
    <tr>
     <td><font face="{font}">{lang_data}:</font></td>
     <td><font face="{font}"><input name="cat_data" size="50" value="{cat_data}"></font></td>
    </tr>
    <tr>
     <td><font face="{font}">{lang_access}:</font></td>
     <td colspan=2>{access}</td>
    </tr>
    </table>

<!-- BEGIN add -->
         <table width="50%" border="0" cellspacing="2" cellpadding="2">
         <tr valign="bottom">
          <td height="50" align="right">
           {hidden_vars}
           <font face="{font}"><input type="submit" name="submit" value="{lang_add}"></font></td>
          <td height="50" align="center">
           <font face="{font}"><input type="reset" name="reset" value="{lang_reset}"></font></form></td>
          <td height="50">
            <form method="POST" action="{doneurl}">
           {hidden_vars}
         <font face="{font}"><input type="submit" name="done" value="{lang_done}"></font></form></td>
         </tr>
         </table>
         </form>
         </center>
<!-- END add -->

<!-- BEGIN edit -->
         <table width="50%" border="0" cellspacing="2" cellpadding="2">
         <tr valign="bottom">
          <td height="50" align="right">
           {hidden_vars}
           <font face="{font}"><input type="submit" name="submit" value="{lang_edit}"></font></form></td>
          <td height="50" align="center">
            <form method="POST" action="{deleteurl}">
           {hidden_vars}
         <font face="{font}"><input type="submit" name="delete" value="{lang_delete}"></font></form></td>
          <td height="50">
            <form method="POST" action="{doneurl}">
           {hidden_vars}
         <font face="{font}"><input type="submit" name="done" value="{lang_done}"></font></form></td>
         </tr>
         </table>
         </center>
<!-- END edit -->
<!-- END form -->