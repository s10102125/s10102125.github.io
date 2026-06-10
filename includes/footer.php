    </div><!-- #content -->

    <!-- tiny easter-egg trigger in footer, only visible on exam_scores page -->
    <?php if (($current_page ?? '') === 'exam_scores.php'): ?>
    <div style="padding:6px 28px 14px;text-align:right">
        <span id="easterEggTrigger" title="v1.0.0"
            style="font-size:10px;color:var(--text3);opacity:.3;cursor:default;user-select:none;letter-spacing:1px;font-family:'Space Mono',monospace">
            v1.0.0
        </span>
    </div>
    <?php endif; ?>
</div><!-- #main -->

<script>
(function() {
    // ── Teleport：把所有 .modal-overlay 移到 <body> 最外層 ──
    // 不管它們原本在哪個容器、flex/grid/overflow 父層，全部移出
    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        document.body.appendChild(el);
    });

    // ── Modal 開關 ──
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'flex';
        el.offsetHeight; // force reflow
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(function() {
            if (!el.classList.contains('open')) el.style.display = 'none';
        }, 220);
    }

    // 點 overlay 背景關閉
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay') && e.target.classList.contains('open')) {
            closeModal(e.target.id);
        }
    });

    // ESC 關閉
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function(el) {
                closeModal(el.id);
            });
        }
    });

    // 掛到 window，讓頁面內任何 onclick 都能呼叫
    window.openModal  = openModal;
    window.closeModal = closeModal;

    // Auto-dismiss flash
    setTimeout(function() {
        var f = document.querySelector('.flash');
        if (f) f.remove();
    }, 3100);
})();
</script>
</body>
</html>
