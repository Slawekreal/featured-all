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
        var wrapper = document.querySelector('.featuredall-settings-wrap');
        if (!tabs.length || !contents.length || !wrapper) {
            return;
        }

        wrapper.classList.add('featuredall-tabs-enabled');

        function activate(tabKey) {
            tabs.forEach(function(t) {
                t.classList.toggle('nav-tab-active', t.getAttribute('data-tab') === tabKey);
            });
            contents.forEach(function(c) {
                var isActive = c.getAttribute('data-tab') === tabKey;
                c.classList.toggle('is-active', isActive);
                c.style.display = isActive ? 'block' : 'none';
            });
        }

        var initial = tabs[0].getAttribute('data-tab');
        if (window.location.hash) {
            var hash = window.location.hash.replace('#featuredall-tab-', '').replace('#', '');
            tabs.forEach(function(tab) {
                if (tab.getAttribute('data-tab') === hash) {
                    initial = hash;
                }
            });
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                activate(tab.getAttribute('data-tab'));
            });
        });

        activate(initial);
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
        var previewSelector = wrapper.getAttribute('data-preview-target');
        var preview = previewSelector ? document.querySelector(previewSelector) : null;

        function clamp(val, min, max) {
            var num = parseFloat(val);
            if (isNaN(num)) {
                return min;
            }
            return Math.min(Math.max(num, min), max);
        }

        function applyPreview(mode) {
            if (!preview) {
                return;
            }
            if (mode === 'percent') {
                preview.style.maxWidth = clamp(valueField.value || sliderPercent.value, 25, 100) + '%';
            } else if (mode === 'px') {
                preview.style.maxWidth = clamp(valueField.value || sliderPx.value, 320, 1920) + 'px';
            } else {
                preview.style.maxWidth = '100%';
            }
        }

        function refresh(mode) {
            wrapper.classList.toggle('is-disabled', mode === 'auto');
            sliderPercent.style.display = mode === 'percent' || mode === 'auto' ? 'block' : 'none';
            sliderPx.style.display = mode === 'px' ? 'block' : 'none';
            unit.textContent = mode === 'px' ? 'px' : '%';

            if (mode === 'auto') {
                sliderPercent.value = '100';
                valueField.value = '100';
            } else if (mode === 'percent') {
                valueField.value = clamp(valueField.value || sliderPercent.value, 25, 100);
                sliderPercent.value = valueField.value;
            } else if (mode === 'px') {
                valueField.value = clamp(valueField.value || sliderPx.value, 320, 1920);
                sliderPx.value = valueField.value;
            }

            applyPreview(mode);
        }

        modeSelect.addEventListener('change', function() {
            refresh(modeSelect.value);
        });

        sliderPercent.addEventListener('input', function() {
            valueField.value = sliderPercent.value;
            applyPreview('percent');
        });

        sliderPx.addEventListener('input', function() {
            valueField.value = sliderPx.value;
            applyPreview('px');
        });

        valueField.addEventListener('input', function() {
            var mode = modeSelect.value;
            if (mode === 'percent') {
                var clampedPercent = clamp(valueField.value, 25, 100);
                valueField.value = clampedPercent;
                sliderPercent.value = clampedPercent;
            } else if (mode === 'px') {
                var clampedPx = clamp(valueField.value, 320, 1920);
                valueField.value = clampedPx;
                sliderPx.value = clampedPx;
            }
            applyPreview(mode);
        });

        refresh(modeSelect.value);
    }

    function initAspectPreview() {
        var aspectSelect = document.querySelector('.featuredall-aspect-choice');
        var preview = document.querySelector('#featuredall-layout-preview .featuredall-wrapper');
        if (!aspectSelect || !preview) {
            return;
        }
        var map = {
            '16:9': { ratio: '16/9', fallback: '56.25%' },
            '21:9': { ratio: '21/9', fallback: '42.857%' },
            '4:3': { ratio: '4/3', fallback: '75%' },
            '1:1': { ratio: '1/1', fallback: '100%' },
            'auto': { ratio: '', fallback: '56.25%' }
        };

        function applyAspect(value) {
            var current = map[value] || map['16:9'];
            if (current.ratio) {
                preview.style.setProperty('--featuredall-aspect', current.ratio);
            } else {
                preview.style.removeProperty('--featuredall-aspect');
            }
            preview.style.setProperty('--featuredall-aspect-fallback', current.fallback);
        }

        aspectSelect.addEventListener('change', function() {
            applyAspect(aspectSelect.value);
        });

        applyAspect(aspectSelect.value);
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
        initAspectPreview();
    });
})();
