{info_css}
<table width=98%><tr><td>
	<span class=action>{lang_info_action}</span>
</td><td align=right>
	<span class=action>{add_icons}</span>
</td></tr></table>
<hr noshade width="98%" align="center" size="1">
<center>
<!-- BEGIN projdetails -->
         <table width=95% border=0 cellspacing=1 cellpadding=3>
          <tr bgcolor="{th_bg}">
            <td width="3%" class=list>{lang_type}</td>
            <td width="5%" class=list>{lang_status}</td>
            <td class=list>{lang_subject}</td>
            <td width="10%" class=list>{lang_startdate}<br> <!-- </td>
            <td width="10%" class=list>-->{lang_enddate}</td>
            <td width="10%" class=list>{lang_owner}<br>{lang_datecreated}</td>
            <td width="10%" class=list>{lang_responsible}</td>
          </tr>
           <tr bgcolor="{th_bg}" valign="top">
            <td class=list>{type}</td>
            <td class=list>{status}</td>
            <td class=list>{subject}<br>{des}</td>
            <td class=list>{startdate}<br>{enddate}</td>
            <td class=list>{owner}<br>{datecreated}</td>
            <td class=list">{responsible}</td>
          </tr>
      </table><p>
<!-- END projdetails -->

<!-- BEGIN cat_selection -->
<table><tr valign="middle">
<td>
   <form method="POST" name="cat" action="{cat_form}">
      {lang_category}
       <select name="cat_filter" onChange="this.form.submit();">
           <option value="0">{lang_all}</option>
           {categories}
       </select>
       <noscript><input type="submit" name="cats" value="{lang_select}"></noscript>
   </form>
</td><td>
   {total_matchs}
</td>
</tr></table>
<!-- END cat_selection -->

{next_matchs}

  <table width=95% border=0 cellspacing=1 cellpadding=3>
    <tr bgcolor="{th_bg}">
      <td width="2%" class=list>{lang_type}</td>
      <td width="4%" class=list>{lang_status}</td>
      <td class=list>{lang_subject}</td>
      <td width="8%" class=list>{lang_startdate}<br>{lang_enddate}</td>
      <td width="8%" class=list>{lang_owner}<br>{lang_datecreated}</td>
      <td width="8%" class=list>{lang_responsible}</td>
      <td width="3%" class=list>{h_lang_sub}</td>
      <td width="3%" class=list>{h_lang_action}</td>
    </tr>

<!-- BEGIN info_list -->
      <tr bgcolor="{tr_color}" valign="top">
        <td class=list>{type}</td>
        <td class=list>{status}</td>
        <td class=list>{subject}<br>{des}</td>
        <td class=list>{startdate}<br>{enddate}</td>
        <td class=list>{owner}<br>{datecreated}</td>
        <td class=list>{responsible}</td>
        <td class=list>{subadd} 
           {viewsub} 
         {viewparent}</td>
        <td class=list>{edit}&nbsp;{delete}</td>
      </tr>
<!-- END info_list -->

  </table>

 {next_matchs_end}

</center>

 <table><tr><td>
 	{add_button}
 </td><td>
 	{back2projects}
 </td></tr></table>
