const editor = {
	on: () => {},
	mode: {
		get: () => "design",
		set: () => {}
	},
	options: {
		isRegistered: () => false,
		set: () => {}
	},
	formatter: {
		apply: () => {}
	},
	nodeChanged: () => {},
	getContent: () => "",
	setContent: () => {},
	getBody: () => null,
	getDoc: () => null,
	hasFocus: () => false,
	focus: () => {}
};

const tinymce = {
	overrideDefaults: () => {},
	init: async(config) =>
	{
		config.setup?.(editor);
		return [editor];
	},
	remove: () => {}
};

window.tinymce = tinymce as any;

export default tinymce;
