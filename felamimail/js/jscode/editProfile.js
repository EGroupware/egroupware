var tab = new Tabs(2,'activetab','inactivetab','tab','tabcontent','','','tabpage');

function initAll(_editMode)
{
	tab.init();
	
	switch(_editMode)
	{
		case 'vacation':
			tab.display(2);
			break;
	}
}
