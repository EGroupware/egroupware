{info_css}
 <p class=action>{lang_info_action}<br>

 <center>{error_list}</center>
      <hr noshade width="98%" align="center" size="1">
     <center>
         <table width=95% border=0 cellspacing=1 cellpadding=3>
          <tr class="th">
            <td width="5%" class=list>{lang_type}</td>
            <td width="5%" class=list>{lang_status}</td>
            <td class=list>{lang_subject}</td>
            <td width="10%" class=list>{lang_startdate}</td>
            <td width="10%" class=list>{lang_enddate}</td>
            <td width="10%" class=list>{lang_owner}</td>
            <td width="10%" class=list>{lang_responsible}</td>
          </tr>
           <tr class="th" valign="top">
            <td class=list>{type}</td>
            <td class=list>{status}</td>
            <td class=list>{subject}<br>{des}{filelinks}</td>
            <td class=list>{startdate}</td>
            <td class=list>{enddate}</td>
            <td class=list>{owner}<br>{datemodified}</td>
            <td class=list">{responsible}</td>
          </tr>
      </table><p>

 <center>
  <form method="POST" name="EditorForm" action="{actionurl}" enctype="multipart/form-data">
        {hidden_vars}
        <table width="90%" border="0" cellspacing="0" cellpadding="2">
         <tr>
           <td>{lang_file}</td>
           <td><input type="file" name="attachfile" value=""></td>

           <td>{lang_comment}</td>
           <td><input name="filecomment" size="30" maxlength="64" value=""></td>
         </tr>
        </table>
		  <p>
        <table width="75%" border="0" cellspacing="0" cellpadding="0">
         <tr valign="bottom">
          <td>{submit_button}</form></td>
          <td>{cancel_button}</td>
         </tr>
        </table>
  </center>
</html>
