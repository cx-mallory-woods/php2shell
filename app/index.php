<?php
// Benign demo app. This does NOT exploit or trigger CVE-2026-109618/109619.
// Its only purpose is to give the vulnerable PHP runtime a reason to exist in
// the image so the container is realistic. The vulnerability lives in the PHP
// runtime shipped in the base image, not in this code.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['f'])) {
    echo 'Received: ' . htmlspecialchars($_FILES['f']['name']);
}
?>
<!doctype html>
<meta charset="utf-8">
<title>php2shell container-security demo</title>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="f"><button>Upload</button>
</form>
<p>PHP runtime: <?php echo htmlspecialchars(PHP_VERSION); ?></p>
