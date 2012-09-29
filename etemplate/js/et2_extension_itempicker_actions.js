function itempickerDocumentAction(context, data) {
	var formid = "itempicker_action_form";
	var form = "<form id='" + formid + "' action='index.php?menuaction=" + data.app + "." + data.app + "_merge.download_by_request' method='POST'>"
		+ "<input type='hidden' name='data_document_name' value='" + data.value.name + "' />"
		+ "<input type='hidden' name='data_document_dir' value='" + data.value.dir + "' />"
		+ "<input type='hidden' name='data_checked' value='" + data.checked.join(',') + "' />"
		+ "</form>";
	$j("body").append(form);
	$j("#" + formid).submit().remove();
}