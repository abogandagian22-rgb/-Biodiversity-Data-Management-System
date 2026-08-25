<?php $activePage = $activePage ?? 'dashboard'; ?>

<div class="sidebar p-3">

    <h4 class="text-white">Philippine Biodiversity Dashboard</h4>
    <p class="sidebar-subtitle">PhilBio 2026</p>

    <ul class="nav flex-column mt-4">
        <li><a href="index.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-house-door"></i>Dashboard</a></li>
        <li><a href="birds.php" class="<?= $activePage === 'birds' ? 'active' : '' ?>"><i class="bi bi-feather"></i>Birds Records</a></li>
        <li><a href="bats.php" class="<?= $activePage === 'bats' ? 'active' : '' ?>"><i class="bi bi-cloud"></i>Bats Records</a></li>
        <li><a href="flora.php" class="<?= $activePage === 'flora' ? 'active' : '' ?>"><i class="bi bi-flower1"></i>Flora Records</a></li>
        <li><a href="archive.php" class="<?= $activePage === 'archive' ? 'active' : '' ?>"><i class="bi bi-archive"></i>Archive</a></li>
        <li><a href="audit_logs.php" class="<?= $activePage === 'audit' ? 'active' : '' ?>"><i class="bi bi-journal-text"></i>Audit Logs <span class="admin-pill">Admin</span></a></li>
        <li><a href="users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i>Users <span class="admin-pill">Admin</span></a></li>
    </ul>

    <div class="user-box mt-auto">
        <p>John Doe</p>
        <small>Administrator</small>
    </div>

</div>