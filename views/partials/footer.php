    </main>

    <script src="assets/js/main.js"></script>
    <?php if (!empty($pageScript)): ?>
        <script src="assets/js/<?= e((string) $pageScript) ?>"></script>
    <?php endif; ?>
</body>
</html>
