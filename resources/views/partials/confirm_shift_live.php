<?php
/**
 * Shift bypass for the standalone live views (FOH run sheet, display client,
 * event audio), which do not load layout.php. Defines window.skipConfirm():
 * true while the click being handled was made with Shift (or Shift+Enter/Space
 * on the focused button) and the company-wide switch under Einstellungen ›
 * Sicherheit is on. Per-user switch and required confirmations do not apply here.
 */
?>
<script>
(function () {
    var enabled = <?= \App\Infrastructure\Http\ConfirmPolicy::companyBypassEnabled() ? 'true' : 'false' ?>;
    var shiftDown = false, skip = false;
    document.addEventListener('keydown', function (e) { if (e.key === 'Shift') { shiftDown = true; } }, true);
    document.addEventListener('keyup', function (e) { if (e.key === 'Shift') { shiftDown = false; } }, true);
    window.addEventListener('blur', function () { shiftDown = false; });
    // Capture phase: runs before the page's own click handlers ask their question.
    document.addEventListener('click', function (e) {
        var button = e.target.closest('button, a, input');
        skip = enabled && (e.detail === 0 ? (e.shiftKey || shiftDown) && document.activeElement === button : e.shiftKey);
        setTimeout(function () { skip = false; }, 0);
    }, true);
    window.skipConfirm = function () { return skip; };
})();
</script>
