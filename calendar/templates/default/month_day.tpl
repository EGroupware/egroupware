<!-- $Id$    month_day -->
<!-- BEGIN m_w_table -->
<table width="100%" border="0" bordercolor="#FFFFFF" cellspacing="2" cellpadding="2" cols="{cols}">
 {row}
</table>
<!-- END m_w_table -->
<!-- BEGIN month_daily -->
   <font size="-1">[ {day_number} ]</font>{new_event_link}<br>
    {daily_events}
<!-- END month_daily -->
<!-- BEGIN day_event -->
    <font size="{week_day_font_size}">
{events}
    </font>
<!-- END day_event -->
<!-- BEGIN event -->
{day_events}
<!-- END event -->
