CKEditor Free PHP Image Uploader Addon, `From: <http://coursesweb.net/php-mysql/ckeditor-image-upload_s2>`
=============================

**imgupload** is a `CKEditor <http://ckeditor.com/>`_ addon that can be used to upload images on server with CKEditor.

This addon integrates with the **image** plugin (part of CKEditor),
by making it provide a **Upload** tab-button in the Image dialog window.

To use the upload addon
------------------------

Open the ``imgupload.php`` file and change the value of $upload_dir variable (line 5), to replace the default path to the folder with images-directory on your server, RELATIVE TO THE ROOT OF YOUR WEBSITE ON SERVER.

Copy the ``imgupload.php`` file into ``plugins/`` directory in your CKEditor install.

Sets CHMOD writable permision (0777) to image-upload folder on your server (if on Linux system), to allow PHP to upload the files

Enable the plugin by adding the `filebrowserImageUploadUrl` parameter::

	CKEDITOR.replace('textareaId', {
		"filebrowserImageUploadUrl": "/path_to/ckeditor/plugins/imgupload.php"
	});

