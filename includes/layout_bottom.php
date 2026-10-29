<?php
// includes/layout_bottom.php — closes every page and loads the scripts.
// A page can set $extraScripts (HTML <script> tags) before including this file.

$extraScripts = isset($extraScripts) ? $extraScripts : '';
$isAdminText = is_admin() ? 'true' : 'false';
?>
    </main>
  </div><!-- /.main -->
</div><!-- /.app -->

<div class="scrim" data-sidebar-toggle></div>

<script>window.A2Z = { csrf: "<?= e(csrf_token()) ?>", admin: <?= $isAdminText ?> };</script>
<script src="assets/js/app.js"></script>
<?= $extraScripts ?>
</body>
</html>
