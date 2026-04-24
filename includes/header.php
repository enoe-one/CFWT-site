<?php
// includes/header.php
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
}

// Ne pas afficher le header si en mode maintenance et que l'utilisateur n'est pas admin
if (isset($pdo) && isMaintenanceMode($pdo) && !isAdmin()) {
    return; // Sortir sans afficher le header
}

// Récupérer les paramètres d'apparence
$appearance = getAppearanceSettings($pdo);
?>
<style>
    :root {
        --primary-color: <?php echo htmlspecialchars($appearance['primary_color']); ?>;
        --secondary-color: <?php echo htmlspecialchars($appearance['secondary_color']); ?>;
        --accent-color: <?php echo htmlspecialchars($appearance['accent_color']); ?>;
    }
    
    .bg-primary { background-color: var(--primary-color); }
    .bg-secondary { background-color: var(--secondary-color); }
    .bg-accent { background-color: var(--accent-color); }
    .text-primary { color: var(--primary-color); }
    .text-secondary { color: var(--secondary-color); }
    .text-accent { color: var(--accent-color); }
    .border-primary { border-color: var(--primary-color); }
    .border-secondary { border-color: var(--secondary-color); }
    .border-accent { border-color: var(--accent-color); }
    
    .hover\:bg-primary:hover { background-color: var(--primary-color); }
    .hover\:bg-secondary:hover { background-color: var(--secondary-color); }
    .hover\:text-primary:hover { color: var(--primary-color); }
    
    .gradient-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }
</style>

<!-- Banner de maintenance pour les admins -->
<?php if (isset($pdo) && isMaintenanceMode($pdo) && isAdmin()): ?>
    <div class="bg-red-600 text-white py-3 text-center font-bold">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        MODE MAINTENANCE ACTIF - Seuls les administrateurs peuvent accéder au site
        <i class="fas fa-exclamation-triangle ml-2"></i>
    </div>
<?php endif; ?>

<header class="shadow-lg" style="background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));">
    <nav class="max-w-7xl mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <!-- Logo et titre -->
            <a href="<?php echo SITE_URL; ?>/index.php" class="flex items-center text-white text-2xl font-bold">
                <?php if ($appearance['logo_path']): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($appearance['logo_path']); ?>" 
                         alt="Logo CFWT" class="h-12 mr-3">
                <?php else: ?>
                    <i class="fas fa-shield-alt mr-2"></i>
                <?php endif; ?>
                <span class="hidden md:inline">CFWT</span>
            </a>
            
            <!-- Menu principal -->
            <div class="hidden lg:flex space-x-6">
                <a href="<?php echo SITE_URL; ?>/index.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-home mr-1"></i> Accueil
                </a>
                <a href="<?php echo SITE_URL; ?>/grades.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-star mr-1"></i> Grades
                </a>
                <a href="<?php echo SITE_URL; ?>/diplomes.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-graduation-cap mr-1"></i> Diplômes
                </a>
                <a href="<?php echo SITE_URL; ?>/legions.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-shield-alt mr-1"></i> Divisions
                </a>
                <a href="<?php echo SITE_URL; ?>/members.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-users mr-1"></i> Membres
                </a>
                <a href="<?php echo SITE_URL; ?>/recruitment.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-user-plus mr-1"></i> Recrutement
                </a>
                <a href="<?php echo SITE_URL; ?>/base_defense.php" class="text-white hover:text-gray-200 transition flex items-center">
                    <i class="fas fa-gamepad mr-1"></i> Jeu
                </a>
                <a href="<?php echo SITE_URL; ?>/events.php" class="text-white hover:text-gray-200 transition">
                   <i class="fas fa-calendar-alt mr-1"></i> Événements
                </a>
            </div>
            
            <!-- Boutons de connexion -->
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" 
                       class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition flex items-center">
                        <i class="fas fa-cog mr-2"></i>
                        <span class="hidden md:inline">Admin</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" 
                       class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php elseif (isset($_SESSION['member_id'])): ?>
                    <a href="<?php echo SITE_URL; ?>/member/dashboard.php" 
                       class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        <span class="hidden md:inline">Mon Compte</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" 
                       class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/member_login.php" 
                       class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition flex items-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <span class="hidden md:inline">Connexion</span>
                    </a>
                <?php endif; ?>
                
                <!-- Menu mobile -->
                <button onclick="toggleMobileMenu()" class="lg:hidden text-white">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Menu mobile -->
        <div id="mobile-menu" class="hidden lg:hidden mt-4 space-y-2">
            <a href="<?php echo SITE_URL; ?>/index.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-home mr-2"></i> Accueil
            </a>
            <a href="<?php echo SITE_URL; ?>/grades.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-star mr-2"></i> Grades
            </a>
            <a href="<?php echo SITE_URL; ?>/diplomes.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-graduation-cap mr-2"></i> Diplômes
            </a>
            <a href="<?php echo SITE_URL; ?>/legions.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-shield-alt mr-2"></i> Légions
            </a>
            <a href="<?php echo SITE_URL; ?>/members.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-users mr-2"></i> Membres
            </a>
            <a href="<?php echo SITE_URL; ?>/recruitment.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-user-plus mr-2"></i> Recrutement
            </a>
            <a href="<?php echo SITE_URL; ?>/game.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-gamepad mr-2"></i> Jeu
            </a>
            <a href="<?php echo SITE_URL; ?>/events.php" class="block text-white hover:bg-white hover:bg-opacity-10 px-4 py-2 rounded">
                <i class="fas fa-calendar-alt mr-2"></i> Événements
            </a>
        </div>
    </nav>
</header>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
}
</script>
