<?php
// admin/maintenance_manager.php
require_once '../config.php';

// IMPORTANT: Seul Enoe peut accéder à cette page
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Vérifier que c'est bien Enoe
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || strtolower($user['username']) !== 'enoe') {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Créer la table de maintenance si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_active BOOLEAN DEFAULT FALSE,
        maintenance_type VARCHAR(50) NOT NULL DEFAULT 'scheduled',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        estimated_duration VARCHAR(100),
        start_time DATETIME,
        end_time DATETIME,
        show_countdown BOOLEAN DEFAULT TRUE,
        show_discord_link BOOLEAN DEFAULT TRUE,
        show_progress_bar BOOLEAN DEFAULT FALSE,
        progress_percentage INT DEFAULT 0,
        additional_info TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id),
        INDEX idx_active (is_active),
        INDEX idx_type (maintenance_type)
    )");
    
    // Vérifier et ajouter les colonnes manquantes si la table existe déjà
    $columns = $pdo->query("SHOW COLUMNS FROM maintenance_settings")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('additional_info', $columns)) {
        $pdo->exec("ALTER TABLE maintenance_settings ADD COLUMN additional_info TEXT");
    }
    if (!in_array('show_progress_bar', $columns)) {
        $pdo->exec("ALTER TABLE maintenance_settings ADD COLUMN show_progress_bar BOOLEAN DEFAULT FALSE");
    }
    if (!in_array('progress_percentage', $columns)) {
        $pdo->exec("ALTER TABLE maintenance_settings ADD COLUMN progress_percentage INT DEFAULT 0");
    }
    if (!in_array('start_time', $columns)) {
        $pdo->exec("ALTER TABLE maintenance_settings ADD COLUMN start_time DATETIME");
    }
} catch (PDOException $e) {
    // Table existe déjà ou erreur
}

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // Liste des types valides
        $valid_types = ['server_issue', 'technical_danger', 'scheduled', 'emergency_update', 'custom'];
        
        switch ($action) {
            case 'create_maintenance':
                $type = $_POST['maintenance_type'] ?? 'scheduled';
                
                // Validation du type
                if (!in_array($type, $valid_types)) {
                    throw new Exception("Type de maintenance invalide : " . htmlspecialchars($type));
                }
                
                $title = $_POST['title'];
                $message = $_POST['message'];
                $duration = $_POST['estimated_duration'];
                $additional_info = $_POST['additional_info'] ?? '';
                $show_progress = isset($_POST['show_progress_bar']) ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT INTO maintenance_settings 
                    (maintenance_type, title, message, estimated_duration, additional_info, 
                     show_countdown, show_discord_link, show_progress_bar, created_by) 
                    VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?)");
                $stmt->execute([$type, $title, $message, $duration, $additional_info, $show_progress, $_SESSION['user_id']]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Création maintenance', $title);
                $success = "Maintenance créée avec succès !";
                break;
            
            case 'quick_activate':
                $id = $_POST['maintenance_id'];
                $end_time = $_POST['end_time'] ?? null;
                $start_time = date('Y-m-d H:i:s');
                
                // Désactiver toutes les maintenances
                $pdo->query("UPDATE maintenance_settings SET is_active = 0");
                
                // Activer celle sélectionnée avec l'heure de début et fin
                $stmt = $pdo->prepare("UPDATE maintenance_settings 
                    SET is_active = 1, start_time = ?, end_time = ? 
                    WHERE id = ?");
                $stmt->execute([$start_time, $end_time, $id]);
                
                // Activer dans site_content
                $pdo->prepare("INSERT INTO site_content (page, section, content, updated_by, updated_at) 
                    VALUES ('appearance', 'maintenance_mode', '1', ?, NOW()) 
                    ON DUPLICATE KEY UPDATE content = '1', updated_by = ?, updated_at = NOW()")
                    ->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Activation maintenance', "ID: $id");
                $success = "Maintenance activée avec succès !";
                break;
                
            case 'deactivate_maintenance':
                $pdo->query("UPDATE maintenance_settings SET is_active = 0, end_time = NULL, start_time = NULL, progress_percentage = 0");
                
                // Désactiver dans site_content
                $pdo->prepare("INSERT INTO site_content (page, section, content, updated_by, updated_at) 
                    VALUES ('appearance', 'maintenance_mode', '0', ?, NOW()) 
                    ON DUPLICATE KEY UPDATE content = '0', updated_by = ?, updated_at = NOW()")
                    ->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Désactivation maintenance', '');
                $success = "Maintenance désactivée avec succès !";
                break;
                
            case 'update_maintenance':
                $id = $_POST['maintenance_id'];
                $type = $_POST['maintenance_type'] ?? 'scheduled';
                
                // Validation du type
                if (!in_array($type, $valid_types)) {
                    throw new Exception("Type de maintenance invalide");
                }
                
                $title = $_POST['title'];
                $message = $_POST['message'];
                $duration = $_POST['estimated_duration'];
                $additional_info = $_POST['additional_info'] ?? '';
                $show_progress = isset($_POST['show_progress_bar']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE maintenance_settings 
                    SET maintenance_type = ?, title = ?, message = ?, estimated_duration = ?, 
                        additional_info = ?, show_progress_bar = ?
                    WHERE id = ?");
                $stmt->execute([$type, $title, $message, $duration, $additional_info, $show_progress, $id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Modification maintenance', "ID: $id");
                $success = "Maintenance modifiée avec succès !";
                break;
                
            case 'update_progress':
                $id = $_POST['maintenance_id'];
                $progress = intval($_POST['progress_percentage']);
                
                // Limiter entre 0 et 100
                $progress = max(0, min(100, $progress));
                
                $stmt = $pdo->prepare("UPDATE maintenance_settings SET progress_percentage = ? WHERE id = ?");
                $stmt->execute([$progress, $id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'MAJ progression maintenance', "$progress%");
                $success = "Progression mise à jour : $progress%";
                break;
                
            case 'delete_maintenance':
                $id = $_POST['maintenance_id'];
                
                // Vérifier qu'elle n'est pas active
                $stmt = $pdo->prepare("SELECT is_active FROM maintenance_settings WHERE id = ?");
                $stmt->execute([$id]);
                $maint = $stmt->fetch();
                
                if ($maint && $maint['is_active']) {
                    throw new Exception("Impossible de supprimer une maintenance active !");
                }
                
                $stmt = $pdo->prepare("DELETE FROM maintenance_settings WHERE id = ?");
                $stmt->execute([$id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Suppression maintenance', "ID: $id");
                $success = "Maintenance supprimée avec succès !";
                break;
                
            case 'reset_all':
                // Désactiver toutes les maintenances
                $pdo->query("UPDATE maintenance_settings SET is_active = 0, end_time = NULL");
                
                // Supprimer les maintenances inactives
                $deleted = $pdo->exec("DELETE FROM maintenance_settings WHERE is_active = 0");
                
                // Désactiver dans site_content
                $pdo->prepare("INSERT INTO site_content (page, section, content, updated_by, updated_at) 
                    VALUES ('appearance', 'maintenance_mode', '0', ?, NOW()) 
                    ON DUPLICATE KEY UPDATE content = '0', updated_by = ?, updated_at = NOW()")
                    ->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'Reset complet maintenances', "$deleted supprimées");
                $success = "Toutes les maintenances ont été réinitialisées ! ($deleted supprimées)";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer la maintenance active
$active_maintenance = $pdo->query("SELECT * FROM maintenance_settings WHERE is_active = 1 LIMIT 1")->fetch();

// Récupérer toutes les configurations
$all_maintenances = $pdo->query("SELECT * FROM maintenance_settings ORDER BY 
    is_active DESC, created_at DESC")->fetchAll();

// Récupérer les événements à venir (dans les prochaines 72h)
$upcoming_events = $pdo->query("SELECT * FROM events 
    WHERE date_start >= NOW() 
    AND date_start <= DATE_ADD(NOW(), INTERVAL 72 HOUR) 
    ORDER BY date_start ASC 
    LIMIT 5")->fetchAll();

// Configurations visuelles par type
$type_configs = [
    'server_issue' => [
        'name' => 'Problème Serveur',
        'icon' => 'fa-exclamation-triangle',
        'bg' => 'from-red-900 via-red-800 to-orange-900',
        'border' => 'border-red-500',
        'text' => 'text-red-400',
        'badge' => 'bg-red-600',
        'page_bg' => 'from-red-950 via-gray-900 to-black',
        'title_color' => 'text-red-400',
        'message_bg' => 'from-red-900 to-red-950'
    ],
    'technical_danger' => [
        'name' => 'Danger Technique',
        'icon' => 'fa-radiation-alt',
        'bg' => 'from-red-900 via-pink-900 to-red-900',
        'border' => 'border-pink-500',
        'text' => 'text-pink-400',
        'badge' => 'bg-pink-600',
        'page_bg' => 'from-pink-950 via-gray-900 to-black',
        'title_color' => 'text-pink-400',
        'message_bg' => 'from-pink-900 to-pink-950'
    ],
    'scheduled' => [
        'name' => 'Maintenance Prévue',
        'icon' => 'fa-calendar-check',
        'bg' => 'from-blue-900 via-blue-800 to-indigo-900',
        'border' => 'border-blue-500',
        'text' => 'text-blue-400',
        'badge' => 'bg-blue-600',
        'page_bg' => 'from-blue-950 via-gray-900 to-black',
        'title_color' => 'text-blue-400',
        'message_bg' => 'from-blue-900 to-blue-950'
    ],
    'emergency_update' => [
        'name' => 'Mise à Jour Urgente',
        'icon' => 'fa-rocket',
        'bg' => 'from-purple-900 via-violet-800 to-purple-900',
        'border' => 'border-purple-500',
        'text' => 'text-purple-400',
        'badge' => 'bg-purple-600',
        'page_bg' => 'from-purple-950 via-gray-900 to-black',
        'title_color' => 'text-purple-400',
        'message_bg' => 'from-purple-900 to-purple-950'
    ],
    'custom' => [
        'name' => 'Personnalisée',
        'icon' => 'fa-cog',
        'bg' => 'from-gray-900 via-gray-800 to-gray-900',
        'border' => 'border-gray-500',
        'text' => 'text-gray-400',
        'badge' => 'bg-gray-600',
        'page_bg' => 'from-gray-950 via-gray-900 to-black',
        'title_color' => 'text-gray-400',
        'message_bg' => 'from-gray-800 to-gray-900'
    ]
];

// Stats
$stats = [
    'total' => count($all_maintenances),
    'active' => $active_maintenance ? 1 : 0,
    'inactive' => count($all_maintenances) - ($active_maintenance ? 1 : 0),
    'upcoming_events' => count($upcoming_events)
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Maintenance - CFWT Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/all.min.css">
    <style>
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.5; }
            100% { transform: scale(0.8); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .shake { animation: shake 0.5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-gray-900 min-h-screen">
    <?php include '../includes/header.php'; ?>
    
    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header avec bouton retour -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 sm:mb-8">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-tools text-red-500 mr-2 sm:mr-3"></i>
                        <span class="hidden sm:inline">Gestion de la Maintenance</span>
                        <span class="sm:hidden">Maintenance</span>
                    </h1>
                    <p class="text-gray-400 text-sm sm:text-base">
                        <i class="fas fa-crown text-yellow-500 mr-2"></i>
                        Accès réservé à Enoe
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                    <button onclick="document.getElementById('modal-create').classList.remove('hidden')" 
                            class="flex-1 sm:flex-none bg-green-600 text-white px-4 sm:px-6 py-3 rounded-lg hover:bg-green-700 transition font-bold text-sm sm:text-base">
                        <i class="fas fa-plus mr-2"></i>
                        <span class="hidden sm:inline">Nouvelle</span>
                        <span class="sm:hidden">Créer</span>
                    </button>
                    <a href="dashboard.php" class="flex-1 sm:flex-none bg-gray-700 text-white px-4 sm:px-6 py-3 rounded-lg hover:bg-gray-600 transition text-center font-bold text-sm sm:text-base">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="bg-green-900 border border-green-500 text-green-200 px-4 py-3 rounded mb-6 animate-pulse">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-900 border border-red-500 text-red-200 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
                <div class="bg-gradient-to-br from-blue-900 to-blue-800 p-4 sm:p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-300 text-xs sm:text-sm">Total</p>
                            <p class="text-white text-2xl sm:text-3xl font-bold"><?php echo $stats['total']; ?></p>
                        </div>
                        <i class="fas fa-list text-blue-400 text-2xl sm:text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-green-900 to-green-800 p-4 sm:p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-300 text-xs sm:text-sm">Active</p>
                            <p class="text-white text-2xl sm:text-3xl font-bold"><?php echo $stats['active']; ?></p>
                        </div>
                        <i class="fas fa-check-circle text-green-400 text-2xl sm:text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-gray-800 to-gray-700 p-4 sm:p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-300 text-xs sm:text-sm">Inactives</p>
                            <p class="text-white text-2xl sm:text-3xl font-bold"><?php echo $stats['inactive']; ?></p>
                        </div>
                        <i class="fas fa-pause-circle text-gray-400 text-2xl sm:text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-yellow-900 to-yellow-800 p-4 sm:p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-300 text-xs sm:text-sm">Événements</p>
                            <p class="text-white text-2xl sm:text-3xl font-bold"><?php echo $stats['upcoming_events']; ?></p>
                        </div>
                        <i class="fas fa-calendar text-yellow-400 text-2xl sm:text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- Statut actuel -->
            <div class="bg-gray-800 rounded-lg p-4 sm:p-6 mb-6 sm:mb-8 border-2 <?php echo $active_maintenance ? 'border-red-500' : 'border-green-500'; ?>">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-start space-x-3 sm:space-x-4 w-full sm:w-auto">
                        <?php if ($active_maintenance): ?>
                            <div class="relative mt-1">
                                <div class="w-3 h-3 sm:w-4 sm:h-4 bg-red-500 rounded-full pulse-ring"></div>
                                <div class="w-3 h-3 sm:w-4 sm:h-4 bg-red-500 rounded-full absolute top-0 left-0"></div>
                            </div>
                        <?php else: ?>
                            <div class="w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full mt-1"></div>
                        <?php endif; ?>
                        
                        <div class="flex-1">
                            <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">
                                <?php if ($active_maintenance): ?>
                                    <span class="text-red-500">
                                        <i class="fas fa-exclamation-triangle mr-2 shake"></i>
                                        <span class="hidden sm:inline">MAINTENANCE ACTIVE</span>
                                        <span class="sm:hidden">ACTIF</span>
                                    </span>
                                <?php else: ?>
                                    <span class="text-green-500">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        <span class="hidden sm:inline">SITE OPÉRATIONNEL</span>
                                        <span class="sm:hidden">NORMAL</span>
                                    </span>
                                <?php endif; ?>
                            </h2>
                            
                            <?php if ($active_maintenance): ?>
                                <div class="space-y-1 text-sm sm:text-base">
                                    <p class="text-gray-300">
                                        <i class="fas fa-tag mr-2"></i>
                                        <span class="font-semibold"><?php echo htmlspecialchars($active_maintenance['title']); ?></span>
                                    </p>
                                    <?php if ($active_maintenance['end_time']): ?>
                                        <p class="text-gray-400 text-xs sm:text-sm">
                                            <i class="fas fa-clock mr-2"></i>
                                            Fin: <?php echo date('d/m à H:i', strtotime($active_maintenance['end_time'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex gap-2 w-full sm:w-auto">
                        <?php if ($active_maintenance): ?>
                            <form method="POST" onsubmit="return confirm('Désactiver la maintenance ?');" class="flex-1 sm:flex-none">
                                <input type="hidden" name="action" value="deactivate_maintenance">
                                <button type="submit" class="w-full bg-green-600 text-white px-4 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-green-700 transition font-bold text-sm sm:text-base">
                                    <i class="fas fa-power-off mr-2"></i>
                                    <span class="hidden sm:inline">Désactiver</span>
                                    <span class="sm:hidden">OFF</span>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" onsubmit="return confirm('⚠️ ATTENTION !\n\nCette action va :\n- Désactiver la maintenance en cours\n- Supprimer toutes les maintenances inactives\n\nConfirmer ?');" class="flex-1 sm:flex-none">
                            <input type="hidden" name="action" value="reset_all">
                            <button type="submit" class="w-full bg-red-600 text-white px-4 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-red-700 transition font-bold text-sm sm:text-base">
                                <i class="fas fa-trash-restore mr-2"></i>
                                <span class="hidden sm:inline">Reset Tout</span>
                                <span class="sm:hidden">Reset</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Événements à venir -->
            <?php if (!empty($upcoming_events)): ?>
                <div class="bg-yellow-900 bg-opacity-30 border-2 border-yellow-600 rounded-lg p-4 sm:p-6 mb-6 sm:mb-8">
                    <h3 class="text-lg sm:text-xl font-bold text-yellow-400 mb-4">
                        <i class="fas fa-calendar-exclamation mr-2"></i>
                        Événements dans les 24h
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($upcoming_events as $event): ?>
                                                            <div class="bg-black bg-opacity-30 p-3 sm:p-4 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex-1">
                                    <p class="text-white font-semibold text-sm sm:text-base"><?php echo htmlspecialchars($event['title']); ?></p>
                                    <p class="text-yellow-300 text-xs sm:text-sm">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('d/m/Y à H:i', strtotime($event['date_start'])); ?>
                                    </p>
                                </div>
                                <?php if ($event['is_important']): ?>
                                    <span class="bg-red-600 text-white px-2 py-1 rounded text-xs font-bold self-start sm:self-auto">
                                        IMPORTANT
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des maintenances -->
            <div class="space-y-4 sm:space-y-6">
                <h2 class="text-2xl font-bold text-white">
                    <i class="fas fa-list mr-2 text-blue-500"></i>
                    Maintenances configurées
                </h2>
                
                <?php if (empty($all_maintenances)): ?>
                    <div class="bg-gray-800 rounded-lg p-8 sm:p-12 text-center">
                        <i class="fas fa-inbox text-gray-600 text-5xl sm:text-6xl mb-4"></i>
                        <p class="text-gray-400 text-lg sm:text-xl mb-4">Aucune maintenance configurée</p>
                        <button onclick="document.getElementById('modal-create').classList.remove('hidden')" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-bold">
                            <i class="fas fa-plus mr-2"></i>Créer la première
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grid gap-4 sm:gap-6">
                        <?php foreach ($all_maintenances as $maint): 
                            $config = $type_configs[$maint['maintenance_type']];
                        ?>
                            <div class="bg-gradient-to-br <?php echo $config['bg']; ?> rounded-lg p-4 sm:p-6 border-2 <?php echo $config['border']; ?> 
                                        <?php echo $maint['is_active'] ? 'ring-4 ring-yellow-500' : ''; ?> relative overflow-hidden">
                                
                                <!-- Badge ACTIF -->
                                <?php if ($maint['is_active']): ?>
                                    <div class="absolute top-2 right-2 sm:top-4 sm:right-4">
                                        <span class="bg-yellow-500 text-black px-2 sm:px-4 py-1 sm:py-2 rounded-full text-xs sm:text-sm font-bold pulse-ring">
                                            ● EN DIRECT
                                        </span>
                                    </div>
                                    
                                    <!-- Contrôle progression (si activé) -->
                                    <?php if ($maint['show_progress_bar']): ?>
                                        <div class="absolute bottom-2 right-2 sm:bottom-4 sm:right-4">
                                            <form method="POST" class="flex items-center gap-2 bg-black bg-opacity-50 p-2 rounded-lg">
                                                <input type="hidden" name="action" value="update_progress">
                                                <input type="hidden" name="maintenance_id" value="<?php echo $maint['id']; ?>">
                                                <input type="number" name="progress_percentage" 
                                                       value="<?php echo $maint['progress_percentage']; ?>" 
                                                       min="0" max="100" step="5"
                                                       class="w-16 p-1 text-xs rounded bg-gray-700 text-white border border-gray-600">
                                                <button type="submit" class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="flex flex-col gap-4">
                                    <!-- En-tête -->
                                    <div class="flex items-start gap-3 sm:gap-4">
                                        <div class="flex-shrink-0">
                                            <i class="fas <?php echo $config['icon']; ?> text-4xl sm:text-6xl <?php echo $config['text']; ?>"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="<?php echo $config['badge']; ?> text-white px-2 sm:px-3 py-1 rounded-full text-xs font-bold uppercase inline-block mb-2">
                                                <?php echo $config['name']; ?>
                                            </span>
                                            <h3 class="text-xl sm:text-2xl font-bold text-white mb-2 break-words">
                                                <?php echo htmlspecialchars($maint['title']); ?>
                                            </h3>
                                        </div>
                                    </div>

                                    <!-- Message -->
                                    <div class="bg-black bg-opacity-30 rounded-lg p-3 sm:p-4">
                                        <p class="text-gray-200 text-xs sm:text-sm whitespace-pre-line leading-relaxed break-words">
                                            <?php echo htmlspecialchars($maint['message']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Informations supplémentaires -->
                                    <?php if ($maint['additional_info']): ?>
                                        <div class="bg-blue-900 bg-opacity-30 rounded-lg p-3 sm:p-4 border border-blue-500">
                                            <p class="text-blue-200 text-xs sm:text-sm font-semibold mb-2">
                                                <i class="fas fa-info-circle mr-2"></i>Informations supplémentaires
                                            </p>
                                            <p class="text-gray-300 text-xs sm:text-sm whitespace-pre-line">
                                                <?php echo htmlspecialchars($maint['additional_info']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Barre de progression -->
                                    <?php if ($maint['show_progress_bar'] && $maint['is_active']): ?>
                                        <div class="bg-black bg-opacity-20 rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-gray-300 text-xs sm:text-sm font-semibold">
                                                    <i class="fas fa-tasks mr-2"></i>Progression
                                                </span>
                                                <span class="<?php echo $config['text']; ?> font-bold text-sm sm:text-base">
                                                    <?php echo $maint['progress_percentage']; ?>%
                                                </span>
                                            </div>
                                            <div class="w-full bg-gray-700 rounded-full h-3">
                                                <div class="bg-gradient-to-r from-<?php echo $config['accent_color']; ?>-500 to-<?php echo $config['accent_color']; ?>-700 h-3 rounded-full transition-all duration-500" 
                                                     style="width: <?php echo $maint['progress_percentage']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Infos et actions -->
                                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                                        <div class="bg-black bg-opacity-20 rounded-lg p-2 sm:p-3 flex-1">
                                            <div class="flex items-center justify-between text-xs sm:text-sm">
                                                <span class="text-gray-400">
                                                    <i class="fas fa-clock mr-1 sm:mr-2"></i>
                                                    <span class="hidden sm:inline">Durée:</span>
                                                </span>
                                                <span class="<?php echo $config['text']; ?> font-bold">
                                                    <?php echo htmlspecialchars($maint['estimated_duration']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex gap-2 flex-wrap sm:flex-nowrap">
                                            <?php if (!$maint['is_active']): ?>
                                                <button onclick="openActivationModal(<?php echo $maint['id']; ?>, '<?php echo addslashes($maint['title']); ?>')" 
                                                        class="flex-1 sm:flex-none bg-white text-gray-900 px-3 sm:px-6 py-2 sm:py-3 rounded-lg hover:bg-gray-100 transition font-bold text-xs sm:text-sm">
                                                    <i class="fas fa-play mr-1 sm:mr-2"></i>Activer
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($maint)); ?>)" 
                                                    class="flex-1 sm:flex-none bg-gray-700 bg-opacity-50 text-white px-3 sm:px-4 py-2 sm:py-3 rounded-lg hover:bg-opacity-70 transition text-xs sm:text-sm">
                                                <i class="fas fa-edit mr-1 sm:mr-2 sm:mr-0"></i>
                                                <span class="sm:hidden">Modifier</span>
                                            </button>
                                            
                                            <?php if (!$maint['is_active']): ?>
                                                <form method="POST" onsubmit="return confirm('Supprimer cette maintenance ?');" class="flex-1 sm:flex-none">
                                                    <input type="hidden" name="action" value="delete_maintenance">
                                                    <input type="hidden" name="maintenance_id" value="<?php echo $maint['id']; ?>">
                                                    <button type="submit" class="w-full bg-red-600 bg-opacity-50 text-white px-3 sm:px-4 py-2 sm:py-3 rounded-lg hover:bg-opacity-70 transition text-xs sm:text-sm">
                                                        <i class="fas fa-trash mr-1 sm:mr-2 sm:mr-0"></i>
                                                        <span class="sm:hidden">Suppr</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal: Créer une maintenance -->
    <div id="modal-create" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-gray-800 p-4 sm:p-8 rounded-lg max-w-2xl w-full my-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4 sm:mb-6">
                <i class="fas fa-plus-circle text-green-500 mr-2"></i>Créer une maintenance
            </h2>
            
            <form method="POST" id="create-form" class="space-y-4">
                <input type="hidden" name="action" value="create_maintenance">
                
                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Type *</label>
                    <select name="maintenance_type" required 
                            class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                        <option value="server_issue">⚠️ Problème Serveur</option>
                        <option value="technical_danger">☢️ Danger Technique</option>
                        <option value="scheduled">📅 Maintenance Prévue</option>
                        <option value="emergency_update">🚀 Mise à Jour Urgente</option>
                        <option value="custom">⚙️ Personnalisée</option>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Titre *</label>
                    <input type="text" name="title" required maxlength="255"
                           placeholder="Ex: Maintenance Programmée"
                           class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Message *</label>
                    <textarea name="message" required rows="4"
                              placeholder="Message à afficher..."
                              class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base"></textarea>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Durée estimée *</label>
                    <input type="text" name="estimated_duration" required
                           placeholder="Ex: 30 minutes, 1-2 heures"
                           class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                </div>
                
                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Informations supplémentaires (optionnel)</label>
                    <textarea name="additional_info" rows="3"
                              placeholder="Ex: - Mise à jour base de données&#10;- Installation nouveaux serveurs&#10;- Optimisation performances"
                              class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base"></textarea>
                    <p class="text-gray-400 text-xs mt-1">Sera affiché en encadré bleu sous le message principal</p>
                </div>
                
                <div class="bg-gray-700 p-4 rounded-lg">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="show_progress_bar" class="w-5 h-5 rounded">
                        <div>
                            <span class="text-white font-semibold">
                                <i class="fas fa-tasks mr-2 text-blue-400"></i>
                                Activer la barre de progression
                            </span>
                            <p class="text-gray-400 text-xs mt-1">Permet de mettre à jour le pourcentage d'avancement en temps réel</p>
                        </div>
                    </label>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-bold text-sm sm:text-base">
                        <i class="fas fa-plus mr-2"></i>Créer
                    </button>
                    <button type="button" onclick="document.getElementById('modal-create').classList.add('hidden')" 
                            class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-bold text-sm sm:text-base">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Activation -->
    <div id="modal-activation" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 p-4 sm:p-8 rounded-lg max-w-md w-full border-2 border-red-500">
            <h2 class="text-xl sm:text-2xl font-bold text-white mb-4 sm:mb-6">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Activer la maintenance
            </h2>
            
            <form method="POST" id="activation-form">
                <input type="hidden" name="action" value="quick_activate">
                <input type="hidden" name="maintenance_id" id="activation-id">
                
                <div class="bg-gray-900 p-4 rounded-lg mb-4 sm:mb-6">
                    <p class="text-white font-semibold mb-2 text-sm sm:text-base" id="activation-title"></p>
                    <p class="text-gray-400 text-xs sm:text-sm">
                        <i class="fas fa-users mr-2"></i>
                        Les visiteurs ne pourront plus accéder au site
                    </p>
                </div>

                <div class="mb-4 sm:mb-6">
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">
                        <i class="fas fa-clock mr-2"></i>Fin prévue (optionnel)
                    </label>
                    <input type="datetime-local" name="end_time" id="activation-endtime"
                           class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                </div>

                <div class="grid grid-cols-3 gap-2 mb-4 sm:mb-6">
                    <button type="button" onclick="setQuickTime(30)" 
                            class="bg-gray-700 text-white px-2 py-2 rounded hover:bg-gray-600 transition text-xs sm:text-sm">
                        +30min
                    </button>
                    <button type="button" onclick="setQuickTime(60)" 
                            class="bg-gray-700 text-white px-2 py-2 rounded hover:bg-gray-600 transition text-xs sm:text-sm">
                        +1h
                    </button>
                    <button type="button" onclick="setQuickTime(120)" 
                            class="bg-gray-700 text-white px-2 py-2 rounded hover:bg-gray-600 transition text-xs sm:text-sm">
                        +2h
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white px-4 py-3 sm:py-4 rounded-lg hover:bg-red-700 transition font-bold text-sm sm:text-base">
                        <i class="fas fa-exclamation-triangle mr-2"></i>ACTIVER
                    </button>
                    <button type="button" onclick="closeActivationModal()" 
                            class="flex-1 bg-gray-600 text-white px-4 py-3 sm:py-4 rounded-lg hover:bg-gray-700 transition font-bold text-sm sm:text-base">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Édition -->
    <div id="modal-edit" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-gray-800 p-4 sm:p-8 rounded-lg max-w-2xl w-full my-8">
            <h2 class="text-xl sm:text-2xl font-bold text-white mb-4 sm:mb-6">
                <i class="fas fa-edit text-blue-500 mr-2"></i>Modifier la maintenance
            </h2>
            
            <form method="POST" id="edit-form" class="space-y-4">
                <input type="hidden" name="action" value="update_maintenance">
                <input type="hidden" name="maintenance_id" id="edit-id">
                
                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Type *</label>
                    <select name="maintenance_type" id="edit-type" required 
                            class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                        <option value="server_issue">⚠️ Problème Serveur</option>
                        <option value="technical_danger">☢️ Danger Technique</option>
                        <option value="scheduled">📅 Maintenance Prévue</option>
                        <option value="emergency_update">🚀 Mise à Jour Urgente</option>
                        <option value="custom">⚙️ Personnalisée</option>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Titre *</label>
                    <input type="text" name="title" id="edit-title" required maxlength="255"
                           class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Message *</label>
                    <textarea name="message" id="edit-message" required rows="5"
                              class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base"></textarea>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Durée estimée *</label>
                    <input type="text" name="estimated_duration" id="edit-duration" required
                           class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base">
                </div>
                
                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Informations supplémentaires (optionnel)</label>
                    <textarea name="additional_info" id="edit-additional-info" rows="3"
                              placeholder="Informations complémentaires à afficher..."
                              class="w-full p-3 rounded bg-gray-700 text-white border border-gray-600 text-sm sm:text-base"></textarea>
                </div>
                
                <div class="bg-gray-700 p-4 rounded-lg">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="show_progress_bar" id="edit-progress" class="w-5 h-5 rounded">
                        <span class="text-white font-semibold">
                            <i class="fas fa-tasks mr-2 text-blue-400"></i>
                            Activer la barre de progression
                        </span>
                    </label>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-bold text-sm sm:text-base">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-bold text-sm sm:text-base">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openActivationModal(id, title) {
        document.getElementById('activation-id').value = id;
        document.getElementById('activation-title').textContent = title;
        document.getElementById('modal-activation').classList.remove('hidden');
    }

    function closeActivationModal() {
        document.getElementById('modal-activation').classList.add('hidden');
        document.getElementById('activation-form').reset();
    }

    function setQuickTime(minutes) {
        const now = new Date();
        now.setMinutes(now.getMinutes() + minutes);
        const formatted = now.toISOString().slice(0, 16);
        document.getElementById('activation-endtime').value = formatted;
    }

    function openEditModal(maint) {
        document.getElementById('edit-id').value = maint.id;
        document.getElementById('edit-type').value = maint.maintenance_type;
        document.getElementById('edit-title').value = maint.title;
        document.getElementById('edit-message').value = maint.message;
        document.getElementById('edit-duration').value = maint.estimated_duration;
        document.getElementById('edit-additional-info').value = maint.additional_info || '';
        document.getElementById('edit-progress').checked = maint.show_progress_bar == 1;
        document.getElementById('modal-edit').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('modal-edit').classList.add('hidden');
        document.getElementById('edit-form').reset();
    }

    // Fermer avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeActivationModal();
            closeEditModal();
            document.getElementById('modal-create').classList.add('hidden');
        }
    });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
