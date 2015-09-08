/**
 * Aspell plug-in for CKeditor 4.0
 * Ported from FCKeditor 2.x by Christian Boisjoli, SilenceIT
 * Ported for CKeditor 4.0 by Klaus Leithoff, Stylite AG
 * Requires toolbar, aspell
 */

CKEDITOR.plugins.add('aspell', {
	icons: 'spellcheck',
	init: function (editor) {
		// Create dialog-based command named "aspell"
		editor.addCommand('aspell', new CKEDITOR.dialogCommand('aspell'));
		
		// Add button to toolbar. Not sure why only that name works for me.
		editor.ui.addButton('SpellCheck', {
			label: 'ASpell',
			command: 'aspell',
		});
		
		// Add link dialog code
		CKEDITOR.dialog.add('aspell', this.path + 'dialogs/aspell.js');
		
	},
	requires: ['toolbar']
});

