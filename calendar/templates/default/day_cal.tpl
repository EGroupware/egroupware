<!-- $Id$ -->
<!-- BEGIN day -->
<table width="100%" border="0" cellspacing="0" cellpadding="0">
{row}
</table>
<!-- END day -->
<!-- BEGIN day_row -->
    <tr>{item}
    </tr>
<!-- END day_row -->
<!-- BEGIN day_event_on -->
     <td class="row_on"{extras}>{event}</td>
<!-- END day_event_on -->
<!-- BEGIN day_event_off -->
     <td class="row_off"{extras}>{event}</td>
<!-- END day_event_off -->
<!-- BEGIN day_event_holiday -->
     <td class="cal_holiday"{extras}>{event}</td>
<!-- END day_event_holiday -->
<!-- BEGIN day_time -->
     <td class="th"><nobr>{open_link}{time}{close_link}</nobr></td>
<!-- END day_time -->

