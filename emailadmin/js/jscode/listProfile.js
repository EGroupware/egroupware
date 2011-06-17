function getOrder(tableID) {
	order = '';

	table = document.getElementById(tableID);
	inputElements = table.getElementsByTagName('tr');
	for(i=0; i<inputElements.length; i++)
	{
		//alert(inputElements[i].value);
		if(i>0)
			order = order + ',';
		order = order + inputElements[i].id;
	}
	
	return order;
}
    	
function moveUp(node) {
	// get the row node
	thisRow = node.parentNode.parentNode;
	if(thisRow.previousSibling)
	{
		currentNode = thisRow.previousSibling;
		while(currentNode.nodeType != 1)
		{
			if(!currentNode.previousSibling)
				return;
			currentNode = currentNode.previousSibling;
		}
		thisRow.parentNode.insertBefore(thisRow,currentNode);
		
		//getOrder('tabel1');
	}
}
	
function moveDown(node) {
	// get the row node
	thisRow = node.parentNode.parentNode;
	if(thisRow.nextSibling)
	{
		currentNode = thisRow.nextSibling;
		while(currentNode.nodeType != 1)
		{
			if(!currentNode.nextSibling)
				return;
			currentNode = currentNode.nextSibling;
		}
		thisRow.parentNode.insertBefore(currentNode,thisRow);
		
		//getOrder('tabel1');
	}
}

function saveOrder()
{
	xajax_doXMLHTTP("emailadmin.ajaxemailadmin.setOrder", getOrder('nextMatchBody'));
}
