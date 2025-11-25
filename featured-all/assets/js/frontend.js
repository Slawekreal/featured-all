(function() {
    'use strict';

    function handleFade(wrapper) {
        if (!wrapper.classList.contains('featured-all-fade')) {
            return;
        }

        var media = wrapper.querySelector('video, iframe');
        if (!media) {
            return;
        }

        var markLoaded = function() {
            wrapper.classList.add('is-loaded');
        };

        media.addEventListener('loadeddata', markLoaded, { once: true });
        media.addEventListener('canplay', markLoaded, { once: true });
        media.addEventListener('load', markLoaded, { once: true });
    }

    function handleOverlay(wrapper) {
        var video = wrapper.querySelector('video');
        var overlay = wrapper.querySelector('.featuredall-overlay-play');
        if (!video || !overlay) {
            return;
        }

        var hideOverlay = function() {
            overlay.classList.add('is-hidden');
            wrapper.classList.add('is-playing');
        };

        overlay.addEventListener('click', function() {
            video.play();
        });

        video.addEventListener('play', hideOverlay);
        video.addEventListener('playing', hideOverlay);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.featuredall-wrapper').forEach(function(wrapper) {
            handleFade(wrapper);
            handleOverlay(wrapper);
        });
    });
})();
