
</div><!-- .page-wrap -->

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<!-- CONFIRM DIALOG -->
<div class="confirm-overlay" id="confirm-overlay">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirm-icon">⚠️</div>
    <div class="confirm-title" id="confirm-title">Konfirmasi</div>
    <div class="confirm-text" id="confirm-text">Apakah Anda yakin?</div>
    <div class="confirm-actions">
      <button class="btn btn-danger" id="confirm-ok-btn" onclick="confirmOk()">Ya, Lanjutkan</button>
      <button class="btn btn-outline" onclick="confirmCancel()">Batal</button>
    </div>
  </div>
</div>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <strong><?= APP_NAME ?></strong> v<?= APP_VERSION ?> 
      <span class="footer-sep">•</span>
      Build <?= APP_BUILD ?>
      <span class="footer-sep">•</span>
      <?= APP_DESC ?>
    </div>
    <div class="footer-copy">© <?= date('Y') ?> All rights reserved. Data bersifat pribadi.</div>
  </div>
</footer>

<script src="<?= BASE_URL ?>assets/app.js"></script>
</body>
</html>
