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

    function formatTime(seconds) {
        seconds = Math.floor(seconds || 0);
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + (s < 10 ? '0' + s : s);
    }

    function handleFallbackControls(wrapper) {
        var controls = wrapper.querySelector('.featuredall-fallback-controls');
        var video = wrapper.querySelector('video');
        if (!controls || !video) {
            return;
        }
        var toggle = controls.querySelector('.featuredall-fallback-toggle');
        var timeLabel = controls.querySelector('.featuredall-fallback-time');

        toggle.addEventListener('click', function() {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });

        video.addEventListener('timeupdate', function() {
            timeLabel.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration || 0);
        });

        video.addEventListener('play', function() {
            controls.classList.add('is-playing');
        });

        video.addEventListener('pause', function() {
            controls.classList.remove('is-playing');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.featuredall-wrapper').forEach(function(wrapper) {
            handleFade(wrapper);
            handleOverlay(wrapper);
            handleFallbackControls(wrapper);
        });
    });
})();
