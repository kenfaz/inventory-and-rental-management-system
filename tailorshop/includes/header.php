<?php
// ============================================================
// includes/header.php
// HTML head section — included at top of every module page
// Requires $pageTitle to be set by the including file
// ============================================================

$pageTitle = $pageTitle ?? "Desire's Tailor Shop";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Global styles -->
  <link href="/tailorshop/assets/css/main.css" rel="stylesheet">
</head>
<body>
<div class="wrapper"></div>