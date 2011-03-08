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
