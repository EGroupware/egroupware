function onCheckHandler(_nodeID)
{
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateFolderStatus',_nodeID,tree.isItemChecked(_nodeID));
}

function OnLoadingStart(_nodeID) {
	return true;
}

function onNodeSelect(_nodeID)
{
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.getFolderInfo",_nodeID);
}

function resetACLAddView()
{
	window.xajax_doXMLHTTPsync('felamimail.ajaxfelamimail.addACL', document.getElementById('accountName').value, window.xajax.getFormValues('formAddACL'),document.getElementById('recursive').checked );
	document.getElementById('recursive').checked = false;
	document.getElementById('accountName').value = '';
	opener.updateACLView();
}

function disableACLEdit()
{
	var active = document.getElementById('active');
	active.checked = false;
	active.disabled = true;
}

function updateACLView(activateACL)
{
	xajax_doXMLHTTPsync('felamimail.ajaxfelamimail.updateACLView');

	var active = document.getElementById('active');
	if (activateACL=='disableACL') active.checked = false;
	else active.checked = false;
	onchange_active(active);

}

function onchange_active(_checkbox) 
{
	var selectBoxes = document.getElementsByName('identity');
	var checkBoxes = document.getElementById('aclTable').getElementsByTagName('input');

	if(_checkbox != null && _checkbox.checked)
	{
		document.getElementById('recursive').disabled =false;
		document.getElementById('addACL').disabled =false;
		document.getElementById('removeACL').disabled =false;
		for(i=0; i<selectBoxes.length; i++) {
			selectBoxes[i].disabled = false;
		}
		for(i=0; i<checkBoxes.length; i++) {
			checkBoxes[i].disabled = false;
		}

	}
	else
	{
		document.getElementById('recursive').disabled =true;
		document.getElementById('addACL').disabled =true;
		document.getElementById('removeACL').disabled =true;
		for(i=0; i<selectBoxes.length; i++) {
			selectBoxes[i].disabled = true;
		}
		for(i=0; i<checkBoxes.length; i++) {
			checkBoxes[i].disabled = true;
		}
	}
}

function adaptPresetSelection(_accountid) {
//	'custom'	=> 'custom',
//	'lrs'		=> 'readable',
//	'lprs'		=> 'post',
//	'ilprs'		=> 'append',
//	'cdilprsw'	=> 'write',
//	'acdilprsw'	=>	'all'
	var acl2check = '';
	if (document.getElementById('acl_'+_accountid+'_a').checked) acl2check=acl2check+'a';
	if (document.getElementById('acl_'+_accountid+'_c').checked) acl2check=acl2check+'c';
	if (document.getElementById('acl_'+_accountid+'_d').checked) acl2check=acl2check+'d';
	if (document.getElementById('acl_'+_accountid+'_i').checked) acl2check=acl2check+'i';
	if (document.getElementById('acl_'+_accountid+'_l').checked) acl2check=acl2check+'l';
	if (document.getElementById('acl_'+_accountid+'_p').checked) acl2check=acl2check+'p';
	if (document.getElementById('acl_'+_accountid+'_r').checked) acl2check=acl2check+'r';
	if (document.getElementById('acl_'+_accountid+'_s').checked) acl2check=acl2check+'s';
	if (document.getElementById('acl_'+_accountid+'_w').checked) acl2check=acl2check+'w';
//alert(_accountid+': '+acl2check);
	document.getElementById('predefinedFor_'+_accountid).value = 'custom';
	if (acl2check=='lrs' || acl2check=='lprs' || acl2check=='ilprs' || acl2check=='cdilprsw' ||acl2check=='acdilprsw')
	{
		document.getElementById('predefinedFor_'+_accountid).value = acl2check;
	}
	return true;
}

function displayACLAdd(_url)
{
	egw_openWindowCentered(_url,'felamiMailACL','400','150',window.outerWidth/2,window.outerHeight/2);
}
