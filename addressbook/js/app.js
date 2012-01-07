/**
 * EGroupware addressbook static javascript code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */


/**
 * Add appointment or show calendar for selected contacts, call default nm_action after some checks
 * 
 * @param _action
 * @param _senders
 */
function add_cal(_action, _senders)
{
	if (!_senders[0].id.match(/^[0-9]+$/))
	{
		// send org-view requests to server
		_action.data.nm_action = "submit";
	}
	else
	{
		// call nm_action's popup, but already replace id's in url, because they need to be prefix with a "c"
		if (_action.data.popup) _action.data.nm_action = "popup";
		var ids = "";
		for (var i = 0; i < _senders.length; i++)
		{
			ids += "c" + _senders[i].id + ((i < _senders.length - 1) ? "," : "");
		}
		// we cant just replace $id, as under jdots this can get called multiple times (with already replaced url)!
		_action.data.url = _action.data.url.replace(/(owner|participants)=(0%2C)?[^&]+/,"$1=$2"+ids);
	}
	nm_action(_action, _senders);
}

/**
 * Add task for selected contacts, call default nm_action after some checks
 * 
 * @param _action
 * @param _senders
 */
function add_task(_action, _senders)
{
	if (!_senders[0].id.match(/^[0-9]+$/))
	{
		// send org-view requests to server
		_action.data.nm_action = "submit";
	}
	else
	{
		// call nm_action's popup
		_action.data.nm_action = "popup";
	}
	nm_action(_action, _senders);
}

function showphones(form)
{
	if (form) {
		copyvalues(form,"tel_home","tel_home2");
		copyvalues(form,"tel_work","tel_work2");
		copyvalues(form,"tel_cell","tel_cell2");
		copyvalues(form,"tel_fax","tel_fax2");
	}
}

function hidephones(form)
{
	if (form) {
		copyvalues(form,"tel_home2","tel_home");
		copyvalues(form,"tel_work2","tel_work");
		copyvalues(form,"tel_cell2","tel_cell");
		copyvalues(form,"tel_fax2","tel_fax");
	}
}

function copyvalues(form,src,dst)
{
	var srcelement = getElement(form,src);  //ById("exec["+src+"]");
	var dstelement = getElement(form,dst);  //ById("exec["+dst+"]");
	if (srcelement && dstelement) {
		dstelement.value = srcelement.value;
	}
}

function getElement(form,pattern)
{
	for (i = 0; i < form.length; i++){
		if(form.elements[i].name){
			var found = form.elements[i].name.search("\\["+pattern+"\\]");
			if (found != -1){
				return form.elements[i];
			}
		}
	}
}

function setName(input)
{
	var prefix = document.getElementById("exec[n_prefix]").value;
	var given  = document.getElementById("exec[n_given]").value;
	var middle = document.getElementById("exec[n_middle]").value;
	var family = document.getElementById("exec[n_family]").value;
	var suffix = document.getElementById("exec[n_suffix]").value;
	var org    = document.getElementById("exec[org_name]").value;

	var name = document.getElementById("exec[n_fn]");

	name.value = "";
	if (prefix) name.value += prefix+" ";
	if (given) name.value += given+" ";
	if (middle) name.value += middle+" ";
	if (family) name.value += family+" ";
	if (suffix) name.value += suffix;

	xajax_doXMLHTTP("addressbook.addressbook_ui.ajax_setFileasOptions",prefix,given,middle,family,suffix,org);
}

function add_whole_list(list)
{
	if (document.getElementById("exec[nm][email_type][email_home]").checked == true)
	{
		email_type = "email_home";
	}
	else
	{
		email_type = "email";
	}
	xajax_doXMLHTTP("addressbook.addressbook_ui.ajax_add_whole_list",list,email_type);
}

function setOptions(options_str)
{
	var options = options_str.split("\\b");
	var selbox = document.getElementById("exec[fileas_type]");
	var i;
	for (i=0; i < options.length; i++)
	{
		selbox.options[i].text = options[i];
	}
}

function show_custom_country(selectbox)
{
	if(!selectbox) return;
	custom_field_name = selectbox.name.replace("countrycode", "countryname");
	custom_field = document.getElementById(custom_field_name);
	if(custom_field && selectbox.value == "-custom-") {
		custom_field.style.display = "inline";
	}
	else if (custom_field)
	{
		if(selectbox.value == "" || selectbox.value == null)
		{
			selectbox.value = "-custom-";
			custom_field.style.display = "inline";
		}
		else
		{
			custom_field.style.display = "none";
		}
	}
}
