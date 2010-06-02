CKEDITOR.editorConfig = function( config )
{

	config.toolbar_egw_simple = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_simple_spellcheck = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize','SpellChecker'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_simple_aspell = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize','SpellCheck'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

//	config.toolbar_egw_simple.concat([
//		'/',
//		['Format','Font','FontSize'],
//		['TextColor','BGColor'],
//		['ShowBlocks','-','About']
//	]);

	config.toolbar_egw_extended = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','Image','Table'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_extended_spellcheck = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','SpellChecker','Image','Table'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_extended_aspell = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','SpellCheck','Image','Table'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced = [
		['Source','DocProps','-','Save','NewPage','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced_spellcheck = [
		['Source','DocProps','-','Save','NewPage','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellChecker'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced_aspell = [
		['Source','DocProps','-','Save','NewPage','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellCheck'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

}
