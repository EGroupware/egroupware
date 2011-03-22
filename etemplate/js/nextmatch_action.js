
// An action object interface for the listbox - "inherits" from 
// egwActionObjectInterface
function nextmatchRowAOI(_node)
{
	var aoi = new egwActionObjectInterface();

	aoi.node = _node;

	aoi.checkBox = ($(":checkbox", aoi.node))[0];
	aoi.checkBox.checked = false;

	aoi.doGetDOMNode = function() {
		return aoi.node;
	}

	// Now append some action code to the node
	$(_node).click(function(e) {
		if (e.target != aoi.checkBox)
		{
			var selected = egwBitIsSet(aoi.getState(), EGW_AO_STATE_SELECTED);
			var state = egwGetShiftState(e);

			aoi.updateState(EGW_AO_STATE_SELECTED,
				!egwBitIsSet(state, EGW_AO_SHIFT_STATE_MULTI) || !selected,
				state);
		}
	});

	$(aoi.checkBox).change(function() {
		aoi.updateState(EGW_AO_STATE_SELECTED, this.checked, EGW_AO_SHIFT_STATE_MULTI);
	});

	aoi.doSetState = function(_state) {
		var selected = egwBitIsSet(_state, EGW_AO_STATE_SELECTED);
		this.checkBox.checked = selected;
		$(this.node).toggleClass('focused',
			egwBitIsSet(_state, EGW_AO_STATE_FOCUSED));
		$(this.node).toggleClass('selected',
			selected);
	}

	return aoi;
}

