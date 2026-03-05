<?php
// $pageTitle, $activePage, $isLoggedIn must be set before including this
$pageTitle  = $pageTitle  ?? 'PortoFolio';
$activePage = $activePage ?? '';
$isLoggedIn = $isLoggedIn ?? isLoggedIn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a href="<?= BASE_URL ?>index.php" class="nav-brand">
      <div class="brand-icon">📊</div>
      <div>
        <div class="brand-name">Porto<span>Folio</span></div>
        <div class="brand-sub">v<?= APP_VERSION ?> &bull; <?= APP_BUILD ?></div>
      </div>
    </a>
    <div class="nav-links">
      <a href="<?= BASE_URL ?>index.php" class="nav-link <?= $activePage==='overview'?'active':'' ?>">
        📊 Overview
      </a>
      <a href="<?= BASE_URL ?>suggestions.php" class="nav-link <?= $activePage==='suggestions'?'active':'' ?>">
        💡 Saran
      </a>
    </div>
    <div class="nav-right">
      <?php if($isLoggedIn): ?>
        <span class="nav-user">👤 <?= htmlspecialchars(ADMIN_USERNAME) ?></span>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">📋 Kelola</a>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline btn-sm">⏏ Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php?redirect=dashboard.php" class="btn btn-gold btn-sm">🔐 Kelola</a>
      <?php endif; ?>
    </div>
    <button class="nav-burger" onclick="toggleMobileNav()" id="nav-burger">☰</button>
  </div>
  <div class="mobile-nav" id="mobile-nav">
    <a href="<?= BASE_URL ?>index.php" class="mobile-nav-link <?= $activePage==='overview'?'active':'' ?>">📊 Overview</a>
    <a href="<?= BASE_URL ?>suggestions.php" class="mobile-nav-link <?= $activePage==='suggestions'?'active':'' ?>">💡 Saran</a>
    <?php if($isLoggedIn): ?>
      <a href="<?= BASE_URL ?>dashboard.php" class="mobile-nav-link <?= $activePage==='dashboard'?'active':'' ?>">📋 Kelola / Dashboard</a>
      <a href="<?= BASE_URL ?>logout.php" class="mobile-nav-link" style="color:var(--red)">⏏ Logout</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>login.php?redirect=dashboard.php" class="mobile-nav-link" style="color:var(--gold)">🔐 Login / Kelola</a>
    <?php endif; ?>
  </div>
</nav>
<div class="page-wrap">
