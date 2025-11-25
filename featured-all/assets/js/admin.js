(function() {
    'use strict';

    function setupMediaButton(button, options) {
        var frame;
        button.addEventListener('click', function() {
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: options.title,
                button: { text: options.button },
                library: { type: options.type },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                if (!attachment) {
                    return;
                }

                if (options.attachmentField) {
                    options.attachmentField.value = attachment.id;
                }

                if (options.urlField && attachment.url) {
                    options.urlField.value = attachment.url;
                }

                if (options.preview && attachment.sizes) {
                    var url = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    options.preview.innerHTML = '<img src="' + url + '" alt="" />';
                }

                if (options.fileLabel) {
                    options.fileLabel.textContent = attachment.filename || '';
                }
            });

            frame.open();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var videoButtons = document.querySelectorAll('.featuredall-select-video');
        videoButtons.forEach(function(btn) {
            var urlField = document.querySelector(btn.getAttribute('data-target'));
            var attachmentField = document.querySelector(btn.getAttribute('data-attachment'));
            var fileLabel = btn.parentElement ? btn.parentElement.querySelector('.featuredall-selected-file') : null;
            setupMediaButton(btn, {
                title: btn.textContent,
                button: btn.textContent,
                type: 'video',
                urlField: urlField,
                attachmentField: attachmentField,
                fileLabel: fileLabel
            });
        });

        var posterButtons = document.querySelectorAll('.featuredall-select-poster');
        posterButtons.forEach(function(btn) {
            var attachmentField = document.querySelector(btn.getAttribute('data-target'));
            var preview = document.querySelector(btn.getAttribute('data-preview'));
            setupMediaButton(btn, {
                title: btn.textContent,
                button: btn.textContent,
                type: 'image',
                attachmentField: attachmentField,
                preview: preview
            });
        });
    });
})();
