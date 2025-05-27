// Stub for shortcut-buttons-flatpickr plugin
import sinon from "sinon";

const ShortcutButtonsPlugin = sinon.stub().callsFake((options = {}) =>
{
	return {
		onReady: sinon.stub(),
		onOpen: sinon.stub(),
		onClose: sinon.stub(),
		onValueUpdate: sinon.stub(),
		options: {
			theme: 'light',
			...options
		},
		defaultButtonCfg: {
			theme: 'light'
		},
		buttonContainer: document.createElement('div'),
		renderButtons: sinon.stub()
	};
});

// Add static properties that the plugin normally has
ShortcutButtonsPlugin.theme = {
	light: 'light',
	dark: 'dark'
};

export default ShortcutButtonsPlugin;