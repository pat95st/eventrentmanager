<?php
/**
 * Lightbox for image galleries (included once per page).
 *
 * Triggers are elements with data-gallery="<group>" and data-gallery-src;
 * optional data-gallery-caption and data-gallery-download (URL offered as a
 * download link). All triggers sharing the same group form one gallery that
 * can be browsed with the arrow buttons, the arrow keys or by swiping.
 * Logic lives in /assets/js/image-gallery.js.
 */

// `use function` imports are per file, so the including view's imports do
// not apply here.
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

if (!empty($GLOBALS['__imageGalleryModalRendered'])) {
    return;
}
$GLOBALS['__imageGalleryModalRendered'] = true;
?>
<style>
  .image-gallery-trigger { cursor: zoom-in; padding: 0; border: 0; background: none; }
  dialog.image-gallery { width: 100vw; height: 100vh; max-width: 100vw; max-height: 100vh; margin: 0; padding: 0; border: 0; background: transparent; overflow: hidden; }
  dialog.image-gallery::backdrop { background-color: rgba(2, 6, 23, 0.88); }
  .image-gallery__stage { position: relative; display: flex; height: 100%; width: 100%; flex-direction: column; align-items: center; justify-content: center; padding: 3.5rem 4rem 1rem; box-sizing: border-box; }
  .image-gallery__img { flex: 0 1 auto; min-height: 0; max-width: 100%; max-height: calc(100vh - 8rem); object-fit: contain; border-radius: 0.5rem; box-shadow: 0 24px 48px -12px rgba(0, 0, 0, 0.6); user-select: none; -webkit-user-drag: none; }
  .image-gallery__btn { position: absolute; display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border: 0; border-radius: 9999px; background: rgba(255, 255, 255, 0.12); color: #fff; font-size: 1.75rem; line-height: 1; cursor: pointer; transition: background-color 120ms ease; }
  .image-gallery__btn:hover { background: rgba(255, 255, 255, 0.25); }
  .image-gallery__btn:focus-visible { outline: 2px solid rgb(var(--color-brand-500)); outline-offset: 2px; }
  .image-gallery__btn[hidden] { display: none; }
  .image-gallery__prev { left: 1rem; top: 50%; transform: translateY(-50%); }
  .image-gallery__next { right: 1rem; top: 50%; transform: translateY(-50%); }
  .image-gallery__close { right: 1rem; top: 0.75rem; }
  .image-gallery__counter { position: absolute; left: 1rem; top: 1.25rem; color: rgba(255, 255, 255, 0.8); font-size: 0.875rem; font-variant-numeric: tabular-nums; }
  .image-gallery__footer { flex: 0 0 auto; max-width: min(60rem, 100%); max-height: 30vh; overflow-y: auto; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.75rem; min-height: 2rem; margin-top: 0.75rem; color: rgba(255, 255, 255, 0.85); font-size: 0.875rem; }
  .image-gallery__caption { white-space: pre-line; text-align: center; }
  .image-gallery__footer a { color: #fff; font-weight: 500; text-decoration: underline; }
  @media (max-width: 640px) {
    dialog.image-gallery { margin: 0; width: 100vw; max-width: 100vw !important; }
    .image-gallery__stage { padding: 3.5rem 0.5rem 1rem; }
    .image-gallery__prev { left: 0.5rem; }
    .image-gallery__next { right: 0.5rem; }
    .image-gallery__prev, .image-gallery__next { background: rgba(0, 0, 0, 0.45); }
  }
</style>
<dialog id="image-gallery" class="image-gallery" aria-label="<?= e(t('gallery.title')) ?>">
    <div class="image-gallery__stage" data-image-gallery-stage>
        <span class="image-gallery__counter" data-image-gallery-counter aria-live="polite"></span>
        <button type="button" class="image-gallery__btn image-gallery__close" data-image-gallery-close aria-label="<?= e(t('gallery.close')) ?>" title="<?= e(t('gallery.close')) ?>">&times;</button>
        <button type="button" class="image-gallery__btn image-gallery__prev" data-image-gallery-prev aria-label="<?= e(t('gallery.previous')) ?>" title="<?= e(t('gallery.previous')) ?>">&#8249;</button>
        <img class="image-gallery__img" data-image-gallery-img src="" alt="">
        <button type="button" class="image-gallery__btn image-gallery__next" data-image-gallery-next aria-label="<?= e(t('gallery.next')) ?>" title="<?= e(t('gallery.next')) ?>">&#8250;</button>
        <div class="image-gallery__footer">
            <span class="image-gallery__caption" data-image-gallery-caption></span>
            <a href="#" download data-image-gallery-download hidden><?= e(t('gallery.download')) ?></a>
        </div>
    </div>
</dialog>
<script src="/assets/js/image-gallery.js" defer></script>
