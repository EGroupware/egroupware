(function($) {
        jQuery.fn.html5_upload = function(options) {

                var available_events = ['onStart', 'onStartOne', 'onProgress', 'onFinishOne', 'onFinish', 'onError'];
                var options = jQuery.extend({
                        onStart: function(event, total) {
                                return true;
                        },
                        onStartOne: function(event, name, number, total) {
                                return true;
                        },
                        onProgress: function(event, progress, name, number, total) {
                        },
                        onFinishOne: function(event, response, name, number, total) {
                        },
                        onFinish: function(event, total) {
                        },
                        onError: function(event, name, error) {
                        },
                        onBrowserIncompatible: function() {
                                alert("Sorry, but your browser is incompatible with uploading files using HTML5 (at least, with current preferences.\n Please install the latest version of Firefox, Safari or Chrome");
                        },
                        autostart: true,
                        autoclear: true,
                        stopOnFirstError: false,
                        sendBoundary: false,
                        fieldName: 'user_file[]',//ignore if sendBoundary is false
                        method: 'post',

                        STATUSES: {
                                'STARTED':              'Запуск',
                                'PROGRESS':             'Загрузка',
                                'LOADED':               'Обработка',
                                'FINISHED':             'Завершено'
                        },
                        headers: {
                                "Cache-Control":"no-cache",
                                "X-Requested-With":"XMLHttpRequest",
                                "X-File-Name": function(file){return file.fileName ? file.fileName : file.name},
                                "X-File-Size": function(file){return file.fileSize ? file.fileSize : file.size},
                                "Content-Type": function(file){
                                        if (!options.sendBoundary) return 'multipart/form-data';
                                        return false;
                                }
                        },


                        setName: function(text) {},
                        setStatus: function(text) {},
                        setProgress: function(value) {},

                        genName: function(file, number, total) {
                                return file + "(" + (number+1) + " из " + total + ")";
                        },
                        genStatus: function(progress, finished) {
                                if (finished) {
                                        return options.STATUSES['FINISHED'];
                                }
                                if (progress == 0) {
                                        return options.STATUSES['STARTED'];
                                }
                                else if (progress == 1) {
                                        return options.STATUSES['LOADED'];
                                }
                                else {
                                        return options.STATUSES['PROGRESS'];
                                }
                        },
                        genProgress: function(loaded, total) {
                                return loaded / total;
                        }
                }, options);

                function upload() {
                        var files = this.files;
                        var total = files.length;
                        var $this = $(this);
                        if (!$this.triggerHandler('onStart.html5_upload', [total])) {
                                return false;
                        }
                        this.disabled = true;
                        var uploaded = 0;
                        var xhr = this.html5_upload['xhr'];
                        this.html5_upload['continue_after_abort'] = true;
                        function upload_file(number) {
                                if (number == total) {
                                        $this.trigger('onFinish.html5_upload', [total]);
                                        options.setStatus(options.genStatus(1, true));
                                        $this.attr("disabled", false);
                                        if (options.autoclear) {
                                                $this.val("");
                                        }
                                        return;
                                }
                                var file = files[number];
				var fileName = file.fileName ? file.fileName : file.name;
				var fileSize = file.fileSize ? file.fileSize : file.size;
                                if (!$this.triggerHandler('onStartOne.html5_upload', [fileName, number, total])) {
                                        return upload_file(number+1);
                                }
                                options.setStatus(options.genStatus(0));
                                options.setName(options.genName(fileName, number, total));
                                options.setProgress(options.genProgress(0, fileSize));
                                xhr.upload['onprogress'] = function(rpe) {
                                        $this.trigger('onProgress.html5_upload', [rpe.loaded / rpe.total, fileName, number, total]);
                                        options.setStatus(options.genStatus(rpe.loaded / rpe.total));
                                        options.setProgress(options.genProgress(rpe.loaded, rpe.total));
                                };
                                xhr.onload = function(load) {
                                        $this.trigger('onFinishOne.html5_upload', [xhr.responseText, fileName, number, total]);
                                        options.setStatus(options.genStatus(1, true));
                                        options.setProgress(options.genProgress(fileSize, fileSize));
                                        upload_file(number+1);
                                };
                                xhr.onabort = function() {
                                        if ($this[0].html5_upload['continue_after_abort']) {
                                                upload_file(number+1);
                                        }
                                        else {
                                                $this.attr("disabled", false);
                                                if (options.autoclear) {
                                                        $this.val("");
                                                }
                                        }
                                };
                                xhr.onerror = function(e) {
                                        $this.trigger('onError.html5_upload', [fileName, e]);
                                        if (!options.stopOnFirstError) {
                                                upload_file(number+1);
                                        }
                                };
                                xhr.open(options.method, typeof(options.url) == "function" ? options.url(number) : options.url, true);
                                $.each(options.headers,function(key,val){
                                        val = typeof(val) == "function" ? val(file) : val; // resolve value
                                        if (val === false) return true; // if resolved value is boolean false, do not send this header
                                        xhr.setRequestHeader(key, val);
                                });

                                if (!options.sendBoundary) {
                                        xhr.send(file);
                                }
                                else {
                                        if (window.FormData) {//Many thanks to scottt.tw
                                                var f = new FormData();
                                                f.append(typeof(options.fieldName) == "function" ? options.fieldName() : options.fieldName, file);
                                                if(typeof(options.beforeSend) == "function") { options.beforeSend(f);} // Give eGW a chance to interfere
                                                xhr.send(f);
                                        }
                                        else if (file.getAsBinary) {//Thanks to jm.schelcher
                                                var boundary = '------multipartformboundary' + (new Date).getTime();
                                                var dashdash = '--';
                                                var crlf     = '\r\n';

                                                /* Build RFC2388 string. */
                                                var builder = '';

                                                builder += dashdash;
                                                builder += boundary;
                                                builder += crlf;

                                                builder += 'Content-Disposition: form-data; name="'+(typeof(options.fieldName) == "function" ? options.fieldName() : options.fieldName)+'"';

                                                //thanks to oyejo...@gmail.com for this fix
                                                fileName = unescape(encodeURIComponent(fileName)); //encode_utf8

                                                builder += '; filename="' + fileName + '"';
                                                builder += crlf;

                                                builder += 'Content-Type: application/octet-stream';
                                                builder += crlf;
                                                builder += crlf;

                                                /* Append binary data. */
                                                builder += file.getAsBinary();
                                                builder += crlf;

						// Give eGW a chance to interfere
						if(typeof(options.beforeSend) == "function") { 
							builder += dashdash;
							builder += boundary;
							builder += crlf;

							builder+=options.beforeSend();
							builder += crlf;
						}

                                                /* Write boundary. */
                                                builder += dashdash;
                                                builder += boundary;
                                                builder += dashdash;
                                                builder += crlf;

                                                xhr.setRequestHeader('content-type', 'multipart/form-data; boundary=' + boundary);
                                                xhr.sendAsBinary(builder);
                                        }
                                        else {
                                                options.onBrowserIncompatible();
                                        }
                                }
                        }
                        upload_file(0);
                        return true;
                }

                return this.each(function() {
                        this.html5_upload = {
                                xhr:                                    new XMLHttpRequest(),
                                continue_after_abort:   true
                        };
                        if (options.autostart) {
                                $(this).bind('change', upload);
                        }
                        for (event in available_events) {
                                if (options[available_events[event]]) {
                                        $(this).bind(available_events[event]+".html5_upload", options[available_events[event]]);
                                }
                        }
                        $(this)
                                .bind('start.html5_upload', upload)
                                .bind('cancelOne.html5_upload', function() {
                                        this.html5_upload['xhr'].abort();
                                })
                                .bind('cancelAll.html5_upload', function() {
                                        this.html5_upload['continue_after_abort'] = false;
                                        this.html5_upload['xhr'].abort();
                                })
                                .bind('destroy.html5_upload', function() {
                                        this.html5_upload['continue_after_abort'] = false;
                                        this.xhr.abort();
                                        delete this.html5_upload;
                                        $(this).unbind('.html5_upload').unbind('change', upload);
                                });
                });
        };
})(jQuery);
