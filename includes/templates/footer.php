<?php
/**
 * MedWell Pharmacy - Footer Template
 * 
 * Application footer with version info.
 */
declare(strict_types=1);
?>

<!-- Footer -->
<footer class="app-footer">
    <div class="footer-content">
        <p>&copy; <?= date('Y') ?> <strong>MedWell Pharmacy</strong>. All rights reserved.</p>
        <p class="footer-version">v<?= APP_VERSION ?></p>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/charts.js"></script>
