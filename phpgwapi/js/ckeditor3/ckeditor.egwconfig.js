CKEDITOR.editorConfig = function( config )
{

	config.toolbar_egw_simple = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize'],
		'/',
		['Find','Replace','-','SelectAll','RemoveFormat'],
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_simple_spellcheck = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize','SpellChecker'],
		'/',
		['Find','Replace','-','SelectAll','RemoveFormat'],
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_simple_aspell = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize','SpellCheck'],
		'/',
		['Find','Replace','-','SelectAll','RemoveFormat'],
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
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','Image','Table'],
		'/',
		['SelectAll','RemoveFormat'],
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_extended_spellcheck = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','SpellChecker','Image','Table'],
		'/',
		['SelectAll','RemoveFormat'],
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_extended_aspell = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize','SpellCheck','Image','Table'],
		'/',
		['SelectAll','RemoveFormat'],
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced = [
		['Source','DocProps','-','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['SelectAll','RemoveFormat'],
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced_spellcheck = [
		['Source','DocProps','-','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellChecker'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

	config.toolbar_egw_advanced_aspell = [
		['Source','DocProps','-','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellCheck'],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image',/*'Flash',*/'Table','HorizontalRule',/*'Smiley',*/'SpecialChar','PageBreak'], //,'UniversalKey'
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	] ;

}
