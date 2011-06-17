function addRow(_selectBoxName, _prompt) {
	result = prompt(_prompt, '');

	if((result == '') || (result == null)) {
		return false;
	}
	
	var newOption = new Option(result, result);
	
	selectBox = document.getElementById(_selectBoxName);
	var length      = selectBox.length;
	
	selectBox.options[length] = newOption;
	selectBox.selectedIndex = length;
}

function editRow(_selectBoxName, _prompt) {
	selectBox = document.getElementById(_selectBoxName);
	
	selectedItem = selectBox.selectedIndex;

	if(selectedItem != null && selectedItem != -1) {
		value = selectBox.options[selectedItem].text;
		result = prompt(_prompt, value);
		
		if((result == '') || (result == null)) {
			return false;
		}
		
		var newOption = new Option(result, result);
		
		selectBox.options[selectedItem] = newOption;
		selectBox.selectedIndex = selectedItem;
	}
}

function removeRow(_selectBoxName) {
	selectBox = document.getElementById(_selectBoxName);
	
	selectedItem = selectBox.selectedIndex;
	if(selectedItem != null) {
		selectBox.options[selectedItem] = null;
	}
	selectedItem--;
	if(selectedItem >= 0) {
		selectBox.selectedIndex = selectedItem;
	} else if (selectBox.length > 0) {
		selectBox.selectedIndex = 0;
	}
}

function selectAllOptions(_selectBoxName) {
	selectBox = document.getElementById(_selectBoxName);

	for(var i=0;i<selectBox.length;i++) {
		selectBox[i].selected=true;
	}

}