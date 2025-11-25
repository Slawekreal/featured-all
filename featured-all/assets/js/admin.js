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

    function initTabs() {
        var tabs = document.querySelectorAll('#featuredall-tabs .nav-tab');
        var contents = document.querySelectorAll('.featuredall-tab-content');
        if (!tabs.length || !contents.length) {
            return;
        }

        function activate(tabKey) {
            tabs.forEach(function(t) {
                t.classList.toggle('nav-tab-active', t.getAttribute('data-tab') === tabKey);
            });
            contents.forEach(function(c) {
                c.style.display = c.getAttribute('data-tab') === tabKey ? 'block' : 'none';
            });
        }

        tabs.forEach(function(tab, index) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                activate(tab.getAttribute('data-tab'));
            });
            if (index === 0) {
                activate(tab.getAttribute('data-tab'));
            }
        });
    }

    function syncWidthControls(wrapper) {
        if (!wrapper) {
            return;
        }
        var modeSelect = wrapper.querySelector('.featuredall-width-mode');
        var sliderPercent = wrapper.querySelector('.featuredall-width-slider');
        var sliderPx = wrapper.querySelector('.featuredall-width-slider-px');
        var valueField = wrapper.querySelector('.featuredall-width-value');
        var unit = wrapper.querySelector('.featuredall-width-unit');

        function refresh(mode) {
            sliderPercent.style.display = mode === 'percent' ? 'block' : 'none';
            sliderPx.style.display = mode === 'px' ? 'block' : 'none';
            unit.textContent = mode === 'px' ? 'px' : '%';
            if (mode === 'auto') {
                valueField.value = '100';
                unit.textContent = '%';
            }
        }

        modeSelect.addEventListener('change', function() {
            refresh(modeSelect.value);
        });

        sliderPercent.addEventListener('input', function() {
            valueField.value = sliderPercent.value;
        });

        sliderPx.addEventListener('input', function() {
            valueField.value = sliderPx.value;
        });

        refresh(modeSelect.value);
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

        document.querySelectorAll('.featuredall-slider-wrap').forEach(syncWidthControls);
        initTabs();
    });
})();
