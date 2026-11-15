/**
 * Image gallery lightbox (see resources/views/_image_gallery_modal.php).
 *
 * Clicking an element with data-gallery="<group>" opens the dialog showing
 * its data-gallery-src. All triggers of the same group (in DOM order) can be
 * browsed with the prev/next buttons, the left/right arrow keys or a
 * horizontal swipe. Navigation wraps around at both ends.
 */
(function () {
    'use strict';

    var dialog = document.getElementById('image-gallery');
    if (!dialog) { return; }

    var img = dialog.querySelector('[data-image-gallery-img]');
    var counter = dialog.querySelector('[data-image-gallery-counter]');
    var caption = dialog.querySelector('[data-image-gallery-caption]');
    var download = dialog.querySelector('[data-image-gallery-download]');
    var prevBtn = dialog.querySelector('[data-image-gallery-prev]');
    var nextBtn = dialog.querySelector('[data-image-gallery-next]');
    var stage = dialog.querySelector('[data-image-gallery-stage]');

    var items = [];
    var index = 0;

    function collect(group) {
        return Array.prototype.filter.call(
            document.querySelectorAll('[data-gallery]'),
            function (el) { return el.getAttribute('data-gallery') === group; }
        ).map(function (el) {
            return {
                src: el.getAttribute('data-gallery-src'),
                caption: el.getAttribute('data-gallery-caption') || '',
                download: el.getAttribute('data-gallery-download') || ''
            };
        });
    }

    function render() {
        var item = items[index];
        img.src = item.src;
        img.alt = item.caption;
        caption.textContent = item.caption;
        if (item.download) {
            download.href = item.download;
            download.hidden = false;
        } else {
            download.removeAttribute('href');
            download.hidden = true;
        }
        var multiple = items.length > 1;
        prevBtn.hidden = !multiple;
        nextBtn.hidden = !multiple;
        counter.textContent = multiple ? (index + 1) + ' / ' + items.length : '';

        // Preload the neighbours so browsing feels instant.
        if (multiple) {
            [index - 1, index + 1].forEach(function (i) {
                var neighbour = items[(i + items.length) % items.length];
                (new Image()).src = neighbour.src;
            });
        }
    }

    function go(step) {
        if (items.length < 2) { return; }
        index = (index + step + items.length) % items.length;
        render();
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-gallery]');
        if (!trigger) { return; }
        event.preventDefault();
        var group = trigger.getAttribute('data-gallery');
        var all = Array.prototype.filter.call(
            document.querySelectorAll('[data-gallery]'),
            function (el) { return el.getAttribute('data-gallery') === group; }
        );
        items = collect(group);
        index = Math.max(0, all.indexOf(trigger));
        if (items.length === 0) { return; }
        render();
        dialog.showModal();
        if (nextBtn.hidden) {
            dialog.querySelector('[data-image-gallery-close]').focus();
        } else {
            nextBtn.focus();
        }
    });

    prevBtn.addEventListener('click', function () { go(-1); });
    nextBtn.addEventListener('click', function () { go(1); });

    dialog.addEventListener('click', function (event) {
        // Close on the close button or a click on the empty area around the image.
        if (event.target.closest('[data-image-gallery-close]') || event.target === stage || event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') { event.preventDefault(); go(-1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); go(1); }
    });

    var touchX = null;
    var touchY = null;
    stage.addEventListener('touchstart', function (event) {
        if (event.touches.length !== 1) { touchX = null; return; }
        touchX = event.touches[0].clientX;
        touchY = event.touches[0].clientY;
    }, { passive: true });
    stage.addEventListener('touchend', function (event) {
        if (touchX === null) { return; }
        var dx = event.changedTouches[0].clientX - touchX;
        var dy = event.changedTouches[0].clientY - touchY;
        touchX = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            go(dx < 0 ? 1 : -1);
        }
    }, { passive: true });

    dialog.addEventListener('close', function () {
        img.removeAttribute('src');
    });
})();
