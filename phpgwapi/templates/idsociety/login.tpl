<!-- BEGIN login_form -->
<html>
<head>
 <title>{website_title}</title>
</head>
<body bgcolor="AAAAAA" marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" rightmargin="0" leftmargin="0" background="phpgwapi/templates/idsociety/images/content_spacer_middle.gif">


<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
 <tr>
  <td>


      <form method="post" action="{login_url}">
       <table border="0" align="CENTER" width="40%" cellpadding="0" cellspacing="0">
        <tr>
         <td align="center">
         
          <table border="0" cellspacing="0" cellpadding="0" width="100%" background="phpgwapi/templates/idsociety/images/middle_line_spacer.gif">
           <tr>
            <td colspan="3" align="center">{cd}</td>
           </tr>

           <tr>
            <td align="right"><font color="000000">{lang_username}:&nbsp;</font></td>
            <td align="right"><input name="login" value="{cookie}"></td>
            <td width="20%" rowspan="3">&nbsp;</td>
           </tr>

           <tr>
            <td align="right"><font color="000000">{lang_password}:&nbsp;</font></td>
            <td align="right"><input name="passwd" type="password"></td>
           </tr>

           <tr>
            <td colspan="2" align="right">
             <input name="submit" type="image" src="phpgwapi/templates/idsociety/images/login.gif" border="0">
            </td>
           </tr>
          </table>

         </td>
        </tr>
 
        <tr bgcolor="e6e6e6">
         <td align="right">
          <font color="000000" size="-1">{version}</font>
         </td>
        </tr>       
       </table>
      </form>
  
  </td>
 </tr>
</table>

<!--
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
 <tr>
  <td height="30%" valign="bottom" align="center" background="img/main_spacer.gif">
   <form method="post" action="{login_url}">

    <table border="0" cellpadding="0" cellspacing="0" width="60%" height="69" align="center">
     <tr>
      <td align="right" valign="top" background="img/middle_line_spacer.gif">
       <img src="img/little_spacer.gif">
       <br><font size="2" color="#FFFFFF" face="verdana, arial, times new roman">{lang_username}</font>
       <input name="login" value="{cookie}">
       
       <br><font size="2" color="#FFFFFF" face="verdana, arial, times new roman">{lang_password}</font>
       <input name="passwd" type="password">
       
       <br><input name="submit" type="image" src="phpgwapi/templates/idsociety/images/login.gif" border="0">
      </td>
      </form>
      <td width="5" background="img/middle_line_spacer.gif"><spacer type="block" width="5"></spacer></TD>
     </tr>
    </table>

   </td>
  </tr>
  <tr>
   <td height="70%" background="img/main_spacer.gif"><spacer type="block" width="1"></spacer>
  </td>
 </tr>
	
</table> -->

</body>
</html>
<!-- END login_form -->