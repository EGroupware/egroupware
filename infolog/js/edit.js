/**
 * Javascript used on the infolog edit popup
 */

function add_email_from_ab(ab_id,info_cc)
{
	var ab = document.getElementById(ab_id); 
	
	if (!ab || !ab.value)
	{
		set_style_by_class('tr','hiddenRow','display','block');
	}
	else
	{
		var cc = document.getElementById(info_cc); 
		
		for(var i=0; i < ab.options.length && ab.options[i].value != ab.value; ++i) ; 
		
		if (i < ab.options.length)
		{
			cc.value += (cc.value?', ':'')+ab.options[i].text.replace(/^.* <(.*)>$/,'$1');
			ab.value = '';
			ab.onchange();
			set_style_by_class('tr','hiddenRow','display','none');
		}
	}
	return false;
}

/**
 * If one of info_status, info_percent or info_datecompleted changed --> set others to reasonable values
 * 
 * @param string changed_id id of changed element
 * @param string status_id
 * @param string percent_id
 * @param string datecompleted_id
 */
function status_changed(changed_id, status_id, percent_id, datecompleted_id)
{
	var status = document.getElementById(status_id);
	var percent = document.getElementById(percent_id);
	var datecompleted = document.getElementById(datecompleted_id+'[str]');
	if(!datecompleted)
	{
		datecompleted = jQuery('#'+datecompleted_id +' input').get(0);
	}
	var completed;
	
	switch(changed_id)
	{
		case status_id:
			completed = status.value == 'done' || status.value == 'billed';
			if (completed || status.value == 'not-started' || 
				(status.value == 'ongoing') != (percent.value > 0 && percent.value < 100)) 
			{
				percent.value = completed ? 100 : (status.value == 'not-started' ? 0 : 10);
			}
			break;
			
		case percent_id:
			completed = percent.value == 100;
			if (completed != (status.value == 'done' || status.value == 'billed') || 
				(status.value == 'not-started') != (percent.value == 0))
			{
				status.value = percent.value == 0 ? 'not-started' : (percent.value == 100 ? 'done' : 'ongoing');
			}
			break;
			
		case datecompleted_id+'[str]':
		case datecompleted_id:
			completed = datecompleted.value != '';
			if (completed != (status.value == 'done' || status.value == 'billed'))
			{
				status.value = completed ? 'done' : 'not-started';
			}
			if (completed != (percent.value == 100))
			{
				percent.value = completed ? 100 : 0;
			}
			break;
	}
	if (!completed && datecompleted && datecompleted.value != '')
	{
		datecompleted.value = '';
	}
	else if (completed && datecompleted && datecompleted.value == '')
	{
		// todo: set current date in correct format
	}
}
