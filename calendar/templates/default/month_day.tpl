<!-- $Id$    month_day -->

<!-- BEGIN m_w_table -->

<table id="calendar_m_w_table" class="calendar_m_w_table">
	<!-- from month_header.tpl -->
	{row}
</table>
<!-- END m_w_table -->

<!-- BEGIN month_daily -->
<span id="calendar_m_w_table_daynumber" style="font-size:10px">[ {day_number} ]</span>{new_event_link}<br />
    {daily_events}
<!-- END month_daily -->

<!-- BEGIN day_event -->
{events}
<!-- END day_event -->

<!-- BEGIN event -->
{day_events}
<!-- END event -->
