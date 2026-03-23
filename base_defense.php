<?php 
require_once 'config.php';

// Récupérer les paramètres d'apparence pour le style
$appearance = getAppearanceSettings($pdo);

// Générer un ID de session unique pour ce joueur
$gameSessionId = session_id() . '_' . time();

// Déterminer le nom du joueur
$playerName = 'Invité';
if (isset($_SESSION['username'])) {
    $playerName = $_SESSION['username'];
} elseif (isset($_SESSION['member_id'])) {
    $stmt = $pdo->prepare("SELECT discord_pseudo FROM members WHERE id = ?");
    $stmt->execute([$_SESSION['member_id']]);
    $member = $stmt->fetch();
    if ($member) {
        $playerName = $member['discord_pseudo'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base Defense - CFWT Enhanced</title>
    <script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            overflow-x: hidden;
        }
        
        .tower {
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .tower:hover {
            transform: scale(1.1);
            filter: brightness(1.3);
        }
        
        .tower.boosted::after {
            content: '⚡';
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 20px;
            animation: pulse 1s infinite;
        }
        
        .tower.generator::after {
            content: '💰';
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 16px;
            animation: moneyFloat 2s infinite;
        }
        
        @keyframes moneyFloat {
            0%, 100% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(-10px); opacity: 0.6; }
        }
        
        .enemy-unit {
            transition: all 0.1s linear;
            position: relative;
        }
        
        .enemy-health-bar {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 4px;
            background: rgba(0,0,0,0.5);
            border-radius: 2px;
        }
        
        .enemy-health-fill {
            height: 100%;
            background: linear-gradient(90deg, #ef4444, #dc2626);
            border-radius: 2px;
            transition: width 0.2s;
        }
        
        .frozen {
            filter: brightness(0.7) sepia(1) hue-rotate(180deg) saturate(5);
        }
        
        .projectile {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #fbbf24;
            border-radius: 50%;
            box-shadow: 0 0 15px #fbbf24;
            transition: all 0.1s linear;
            z-index: 10;
        }
        
        @keyframes explosion {
            0% { transform: scale(0); opacity: 1; }
            100% { transform: scale(2); opacity: 0; }
        }
        
        .explosion {
            animation: explosion 0.5s ease-out;
            z-index: 15;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        .game-cell {
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        
        .game-cell:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .game-cell.path {
            background: rgba(139, 92, 46, 0.3);
        }
        
        .game-cell.base {
            background: radial-gradient(circle, rgba(34, 197, 94, 0.3), transparent);
        }
        
        .health-bar {
            transition: width 0.3s;
        }
        
        .wave-alert {
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from { transform: translateX(-50%) translateY(-100%); opacity: 0; }
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }
        
        .tower-menu, .ability-btn {
            backdrop-filter: blur(10px);
        }
        
        .ability-cooldown {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 0.5rem;
        }
        
        @keyframes bossShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-2px); }
            75% { transform: translateX(2px); }
        }
        
        .boss {
            animation: bossShake 0.5s infinite;
            filter: drop-shadow(0 0 10px rgba(255, 0, 0, 0.8));
        }
        
        .admin-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 16px;
            z-index: 100;
            box-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @keyframes adminGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.5); }
            50% { box-shadow: 0 0 40px rgba(251, 191, 36, 0.8); }
        }
        
        .admin-btn {
            animation: adminGlow 2s infinite;
        }
        
        .nuke-warning {
            animation: nukeFlash 0.5s infinite;
        }
        
        @keyframes nukeFlash {
            0%, 100% { background: #dc2626; }
            50% { background: #991b1b; }
        }
    </style>
</head>
<body class="min-h-screen py-12">
        <?php include 'includes/header.php'; ?>
    <div class="max-w-7xl mx-auto px-4">
        <!-- Admin Panel Advanced (visible only for Enoe) -->
        <div id="admin-panel" class="admin-panel hidden">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <i class="fas fa-crown text-yellow-400 text-xl mr-2"></i>
                    <h3 class="text-yellow-400 font-bold text-lg">Admin Control Panel</h3>
                </div>
                <button onclick="toggleAdminPanel()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Quick Actions -->
            <div class="mb-4">
                <h4 class="text-white font-bold mb-2 text-sm">⚡ Actions Rapides</h4>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="adminAddMoney()" class="admin-btn bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-bold transition">
                        <i class="fas fa-money-bill-wave mr-1"></i>+1000 💰
                    </button>
                    <button onclick="adminSkipWave()" class="admin-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-bold transition">
                        <i class="fas fa-forward mr-1"></i>Skip
                    </button>
                    <button onclick="adminKillAll()" class="admin-btn bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm font-bold transition">
                        <i class="fas fa-skull mr-1"></i>Kill All
                    </button>
                    <button onclick="adminHealBase()" class="admin-btn bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm font-bold transition">
                        <i class="fas fa-heart mr-1"></i>Heal
                    </button>
                </div>
            </div>
            
            <!-- God Mode -->
            <div class="mb-4">
                <button onclick="adminGodMode()" id="god-mode-btn" class="admin-btn w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded font-bold transition">
                    <i class="fas fa-shield-alt mr-2"></i>God Mode: OFF
                </button>
            </div>
            
            <!-- Enemy Spawner -->
            <div class="mb-4 bg-gray-800 p-3 rounded">
                <h4 class="text-white font-bold mb-2 text-sm">👾 Spawn Ennemis</h4>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <button onclick="adminSpawnEnemy('normal')" class="bg-red-700 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-user-ninja mr-1"></i>Normal
                    </button>
                    <button onclick="adminSpawnEnemy('runner')" class="bg-yellow-700 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-running mr-1"></i>Runner
                    </button>
                    <button onclick="adminSpawnEnemy('tank')" class="bg-blue-700 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-shield mr-1"></i>Tank
                    </button>
                    <button onclick="adminSpawnEnemy('aerial')" class="bg-cyan-700 hover:bg-cyan-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-plane mr-1"></i>Aérien
                    </button>
                    <button onclick="adminSpawnEnemy('boss')" class="bg-purple-700 hover:bg-purple-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-dragon mr-1"></i>Boss
                    </button>
                    <button onclick="adminSpawnEnemy('airboss')" class="bg-indigo-700 hover:bg-indigo-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-plane-departure mr-1"></i>Air Boss
                    </button>
                    <button onclick="adminSpawnEnemy('panda')" class="bg-pink-700 hover:bg-pink-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-paw mr-1"></i>Panda
                    </button>
                </div>
                <div class="flex gap-2">
                    <button onclick="adminSpawnWave()" class="flex-1 bg-orange-700 hover:bg-orange-600 text-white px-2 py-1 rounded text-xs">
                        <i class="fas fa-users mr-1"></i>Spawn Vague x10
                    </button>
                </div>
            </div>
            
            <!-- Special Abilities -->
            <div class="mb-4 bg-gray-800 p-3 rounded">
                <h4 class="text-white font-bold mb-2 text-sm">✨ Capacités Admin</h4>
                <div class="space-y-2">
                    <button onclick="adminUseAbility('airstrike')" class="w-full bg-orange-600 hover:bg-orange-500 text-white px-3 py-2 rounded text-xs">
                        <i class="fas fa-bomb mr-1"></i>Frappe Aérienne
                    </button>
                    <button onclick="adminUseAbility('freeze')" class="w-full bg-cyan-600 hover:bg-cyan-500 text-white px-3 py-2 rounded text-xs">
                        <i class="fas fa-snowflake mr-1"></i>Gel Global
                    </button>
                    <button onclick="adminUseAbility('boost')" class="w-full bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-2 rounded text-xs">
                        <i class="fas fa-bolt mr-1"></i>Boost Tours
                    </button>
                    <button onclick="adminUseAbility('reinforcement')" class="w-full bg-green-600 hover:bg-green-500 text-white px-3 py-2 rounded text-xs">
                        <i class="fas fa-helicopter mr-1"></i>Renforts
                    </button>
                </div>
            </div>
            
            <!-- Game Stats -->
            <div class="bg-gray-800 p-3 rounded">
                <h4 class="text-white font-bold mb-2 text-sm">📊 Statistiques</h4>
                <div class="text-xs space-y-1 text-gray-300">
                    <p>Ennemis actifs: <span id="admin-enemy-count" class="text-red-400 font-bold">0</span></p>
                    <p>Tours actives: <span id="admin-tower-count" class="text-blue-400 font-bold">0</span></p>
                    <p>Projectiles: <span id="admin-projectile-count" class="text-yellow-400 font-bold">0</span></p>
                </div>
            </div>
        </div>

        <!-- En-tête -->
        <div class="text-center mb-8">
            <h1 class="text-5xl font-bold text-white mb-4">
                <i class="fas fa-fort-awesome text-green-500 mr-3"></i>
                Base Defense Enhanced
            </h1>
            <p class="text-gray-400 text-xl">Protégez votre base contre les vagues d'ennemis !</p>
            <?php if (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'enoe'): ?>
                <button onclick="toggleAdminPanel()" class="mt-4 bg-gradient-to-r from-yellow-600 to-orange-600 text-white px-6 py-2 rounded-lg font-bold hover:from-yellow-700 hover:to-orange-700 transition">
                    <i class="fas fa-crown mr-2"></i>Ouvrir Admin Panel
                </button>
            <?php endif; ?>
        </div>

        <!-- Écran d'accueil -->
        <div id="start-screen" class="text-center">
            <div class="bg-gray-900 bg-opacity-90 p-12 rounded-lg max-w-4xl mx-auto">
                <i class="fas fa-shield-alt text-green-500 text-8xl mb-6"></i>
                <h2 class="text-4xl font-bold text-white mb-6">Sélectionnez la Difficulté</h2>
                
                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <button onclick="startGame('easy')" class="bg-green-700 hover:bg-green-600 p-8 rounded-lg transition transform hover:scale-105">
                        <i class="fas fa-smile text-6xl mb-4 text-green-300"></i>
                        <h3 class="text-white font-bold text-2xl mb-2">Facile</h3>
                        <p class="text-gray-300">Argent: 1000</p>
                        <p class="text-gray-300">Vie base: 200</p>
                        <p class="text-gray-300">25 vagues</p>
                    </button>
                    <button onclick="startGame('medium')" class="bg-yellow-700 hover:bg-yellow-600 p-8 rounded-lg transition transform hover:scale-105">
                        <i class="fas fa-meh text-6xl mb-4 text-yellow-300"></i>
                        <h3 class="text-white font-bold text-2xl mb-2">Normal</h3>
                        <p class="text-gray-300">Argent: 600</p>
                        <p class="text-gray-300">Vie base: 100</p>
                        <p class="text-gray-300">30 vagues</p>
                    </button>
                    <button onclick="startGame('hard')" class="bg-red-700 hover:bg-red-600 p-8 rounded-lg transition transform hover:scale-105">
                        <i class="fas fa-skull text-6xl mb-4 text-red-300"></i>
                        <h3 class="text-white font-bold text-2xl mb-2">💀 HARDCORE 💀</h3>
                        <p class="text-gray-300">Argent: 300</p>
                        <p class="text-gray-300">Vie base: 50</p>
                        <p class="text-red-300 font-bold mt-2">35 vagues</p>
                        <p class="text-red-300 font-bold">Boss Panda 1M PV!</p>
                    </button>
                </div>

                <div class="bg-gray-800 p-6 rounded-lg mb-6">
                    <h3 class="text-white font-bold mb-4 text-xl">Types d'Ennemis</h3>
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm">
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-user-ninja text-red-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Normal</p>
                        </div>
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-running text-yellow-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Runner</p>
                        </div>
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-shield text-blue-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Tank</p>
                        </div>
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-plane text-cyan-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Aérien</p>
                        </div>
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-dragon text-purple-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Boss</p>
                        </div>
                        <div class="bg-gray-700 p-3 rounded">
                            <i class="fas fa-plane-departure text-indigo-400 text-2xl mb-2"></i>
                            <p class="text-white font-bold">Air Boss</p>
                            <p class="text-gray-400 text-xs">1000 PV!</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 p-6 rounded-lg">
                    <h3 class="text-white font-bold mb-4 text-xl">Tours et Capacités</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                        <div class="bg-gray-700 p-2 rounded">
                            <i class="fas fa-bomb text-orange-400 text-xl mb-1"></i>
                            <p class="text-white font-bold">Frappe Aérienne</p>
                        </div>
                        <div class="bg-gray-700 p-2 rounded">
                            <i class="fas fa-radiation text-red-600 text-xl mb-1"></i>
                            <p class="text-white font-bold">Bombe Nucléaire</p>
                            <p class="text-red-400">1000💰 - 3000 DMG</p>
                        </div>
                        <div class="bg-gray-700 p-2 rounded">
                            <i class="fas fa-bolt text-yellow-400 text-xl mb-1"></i>
                            <p class="text-white font-bold">Boost</p>
                        </div>
                        <div class="bg-gray-700 p-2 rounded">
                            <i class="fas fa-coins text-yellow-400 text-xl mb-1"></i>
                            <p class="text-white font-bold">Tour Génératrice</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone de jeu -->
        <div id="game-area" class="hidden">
            <!-- HUD -->
            <div class="bg-gray-900 bg-opacity-90 p-6 rounded-lg mb-4">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <p class="text-gray-400 text-sm">Vague</p>
                        <p class="text-white text-3xl font-bold"><span id="wave">1</span>/<span id="max-waves">25</span></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Argent</p>
                        <p class="text-yellow-400 text-3xl font-bold">💰 <span id="money">600</span></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Vie de la Base</p>
                        <div class="w-48 h-6 bg-gray-700 rounded-full overflow-hidden mt-2">
                            <div id="base-health" class="health-bar h-full bg-gradient-to-r from-green-500 to-green-400" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-white text-sm mt-1"><span id="base-health-text">100</span>/<span id="max-health-text">100</span></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Score</p>
                        <p class="text-purple-400 text-3xl font-bold" id="score">0</p>
                    </div>
                    <button onclick="startWave()" id="start-wave-btn"
                            class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-3 rounded-lg font-bold hover:from-red-700 hover:to-red-800 transition">
                        <i class="fas fa-play mr-2"></i>Lancer la Vague
                    </button>
                </div>
            </div>

            <!-- Capacités Spéciales -->
            <div class="bg-gray-900 bg-opacity-90 p-4 rounded-lg mb-4">
                <p class="text-gray-400 text-sm mb-3">Capacités Spéciales :</p>
                <div class="flex flex-wrap gap-3">
                    <button onclick="useAbility('airstrike')" id="ability-airstrike" class="ability-btn relative flex-1 min-w-[130px] bg-orange-700 hover:bg-orange-600 p-3 rounded-lg text-white transition">
                        <i class="fas fa-bomb text-2xl mb-1"></i>
                        <p class="font-bold text-sm">Frappe Aérienne</p>
                        <p class="text-yellow-400 text-xs">💰 150</p>
                    </button>
                    <button onclick="useAbility('freeze')" id="ability-freeze" class="ability-btn relative flex-1 min-w-[130px] bg-cyan-700 hover:bg-cyan-600 p-3 rounded-lg text-white transition">
                        <i class="fas fa-snowflake text-2xl mb-1"></i>
                        <p class="font-bold text-sm">Gel</p>
                        <p class="text-yellow-400 text-xs">💰 100</p>
                    </button>
                    <button onclick="useAbility('boost')" id="ability-boost" class="ability-btn relative flex-1 min-w-[130px] bg-yellow-700 hover:bg-yellow-600 p-3 rounded-lg text-white transition">
                        <i class="fas fa-bolt text-2xl mb-1"></i>
                        <p class="font-bold text-sm">Boost</p>
                        <p class="text-yellow-400 text-xs">💰 120</p>
                    </button>
                    <button onclick="useAbility('reinforcement')" id="ability-reinforcement" class="ability-btn relative flex-1 min-w-[130px] bg-green-700 hover:bg-green-600 p-3 rounded-lg text-white transition">
                        <i class="fas fa-helicopter text-2xl mb-1"></i>
                        <p class="font-bold text-sm">Renforts</p>
                        <p class="text-yellow-400 text-xs">💰 200</p>
                    </button>
                    <button onclick="useAbility('nuke')" id="ability-nuke" class="ability-btn relative flex-1 min-w-[130px] nuke-warning hover:bg-red-800 p-3 rounded-lg text-white transition">
                        <i class="fas fa-radiation text-3xl mb-1"></i>
                        <p class="font-bold text-sm">☢️ NUKE ☢️</p>
                        <p class="text-yellow-400 text-xs">💰 1000</p>
                        <p class="text-xs">3000 DMG + 1M Boss</p>
                    </button>
                </div>
            </div>

            <!-- Alerte de vague -->
            <div id="wave-alert" class="hidden fixed top-1/4 left-1/2 transform -translate-x-1/2 z-50">
                <div class="wave-alert bg-red-600 text-white px-12 py-6 rounded-lg text-center shadow-2xl">
                    <p class="text-5xl font-bold mb-2">VAGUE <span id="wave-number">1</span></p>
                    <p class="text-xl"><span id="enemy-count">10</span> ennemis en approche !</p>
                    <p id="boss-warning" class="text-2xl font-bold mt-2 hidden">⚠️ BOSS INCOMING ⚠️</p>
                    <p id="difficulty-warning" class="text-lg font-bold mt-2 hidden text-red-300"></p>
                </div>
            </div>

            <!-- Menu des tours -->
            <div class="bg-gray-900 bg-opacity-90 p-4 rounded-lg mb-4">
                <p class="text-gray-400 text-sm mb-3">Sélectionnez une tour à construire :</p>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2">
                    <button onclick="selectTower('basic')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-basic">
                        <i class="fas fa-gun text-gray-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Basique</p>
                        <p class="text-yellow-400 text-xs">💰 40</p>
                    </button>
                    <button onclick="selectTower('sniper')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-sniper">
                        <i class="fas fa-crosshairs text-red-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Sniper</p>
                        <p class="text-yellow-400 text-xs">💰 120</p>
                    </button>
                    <button onclick="selectTower('cannon')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-cannon">
                        <i class="fas fa-bomb text-orange-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Canon</p>
                        <p class="text-yellow-400 text-xs">💰 180</p>
                    </button>
                    <button onclick="selectTower('missile')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-missile">
                        <i class="fas fa-rocket text-purple-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Missile Pro</p>
                        <p class="text-yellow-400 text-xs">💰 280</p>
                        <p class="text-gray-400 text-xs">DMG: 120</p>
                    </button>
                    <button onclick="selectTower('laser')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-laser">
                        <i class="fas fa-radiation text-green-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Laser</p>
                        <p class="text-yellow-400 text-xs">💰 300</p>
                    </button>
                    <button onclick="selectTower('antiair')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-antiair">
                        <i class="fas fa-plane-slash text-cyan-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Anti-Air</p>
                        <p class="text-yellow-400 text-xs">💰 150</p>
                    </button>
                    <button onclick="selectTower('generator')" class="tower-menu bg-gray-800 hover:bg-gray-700 p-2 rounded-lg text-white transition border-2 border-transparent" id="tower-generator">
                        <i class="fas fa-coins text-yellow-400 text-xl mb-1"></i>
                        <p class="font-bold text-xs">Générateur</p>
                        <p class="text-yellow-400 text-xs">💰 400</p>
                        <p class="text-green-400 text-xs">+10💰/sec</p>
                    </button>
                </div>
            </div>

            <!-- Terrain de jeu -->
            <div id="game-grid" class="bg-gray-800 rounded-lg p-2 relative" style="width: 100%; height: 700px;">
                <!-- Grille générée dynamiquement -->
            </div>
        </div>

        <!-- Écran Game Over -->
        <div id="game-over-screen" class="hidden text-center">
            <div class="bg-gray-900 bg-opacity-95 p-12 rounded-lg max-w-2xl mx-auto">
                <i id="game-over-icon" class="fas fa-skull-crossbones text-red-500 text-8xl mb-6"></i>
                <h2 id="game-over-title" class="text-4xl font-bold text-white mb-4">Mission Échouée</h2>
                
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <div class="bg-gray-800 p-6 rounded-lg">
                        <p class="text-gray-400 mb-2">Score Final</p>
                        <p class="text-yellow-400 text-4xl font-bold" id="final-score">0</p>
                    </div>
                    <div class="bg-gray-800 p-6 rounded-lg">
                        <p class="text-gray-400 mb-2">Vague Atteinte</p>
                        <p class="text-blue-400 text-4xl font-bold" id="final-wave">0</p>
                    </div>
                    <div class="bg-gray-800 p-6 rounded-lg">
                        <p class="text-gray-400 mb-2">Ennemis Éliminés</p>
                        <p class="text-red-400 text-4xl font-bold" id="total-kills">0</p>
                    </div>
                    <div class="bg-gray-800 p-6 rounded-lg">
                        <p class="text-gray-400 mb-2">Tours Construites</p>
                        <p class="text-green-400 text-4xl font-bold" id="total-towers">0</p>
                    </div>
                </div>

                <div class="flex justify-center space-x-4">
                    <button onclick="restartGame()" 
                            class="bg-gradient-to-r from-green-600 to-green-700 text-white px-8 py-4 rounded-lg font-bold hover:from-green-700 hover:to-green-800 transition">
                        <i class="fas fa-redo mr-2"></i>Rejouer
                    </button>
                    <button onclick="backToStart()" 
                            class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-8 py-4 rounded-lg font-bold hover:from-blue-700 hover:to-blue-800 transition">
                        <i class="fas fa-home mr-2"></i>Menu Principal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Vérifier si l'utilisateur est Enoe (à intégrer avec PHP)
    const isEnoe = <?php echo (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'enoe') ? 'true' : 'false'; ?>;

    // IDs de session injectés par PHP
    const gameSessionId = '<?php echo $gameSessionId; ?>';
    const playerName    = '<?php echo addslashes($playerName); ?>';

    // Configuration du jeu
    const GRID_SIZE = 18;
    const CELL_SIZE = 38;
    
    const TOWER_TYPES = {
        basic: { cost: 40, damage: 8, range: 100, fireRate: 800, icon: 'fa-gun', color: 'text-gray-400', projectileColor: '#9ca3af' },
        sniper: { cost: 120, damage: 40, range: 250, fireRate: 2500, icon: 'fa-crosshairs', color: 'text-red-400', projectileColor: '#f87171' },
        cannon: { cost: 180, damage: 25, range: 130, fireRate: 2000, icon: 'fa-bomb', color: 'text-orange-400', splash: 60, projectileColor: '#fb923c' },
        missile: { cost: 280, damage: 120, range: 200, fireRate: 2500, icon: 'fa-rocket', color: 'text-purple-400', projectileColor: '#c084fc', airCapable: true },
        laser: { cost: 300, damage: 15, range: 140, fireRate: 200, icon: 'fa-radiation', color: 'text-green-400', continuous: true, projectileColor: '#4ade80' },
        antiair: { cost: 150, damage: 60, range: 220, fireRate: 1000, icon: 'fa-plane-slash', color: 'text-cyan-400', airOnly: true, projectileColor: '#22d3ee' },
        generator: { cost: 500, damage: 0, range: 0, fireRate: 0, icon: 'fa-coins', color: 'text-yellow-400', moneyGen: 5 }
    };

    const ENEMY_TYPES = {
        normal: { health: 40, speed: 1.2, reward: 5, icon: 'fa-user-ninja', color: 'text-red-400' },
        runner: { health: 20, speed: 3.8, reward: 10, icon: 'fa-running', color: 'text-yellow-400' },
        tank: { health: 250, speed: 0.6, reward: 20, icon: 'fa-shield', color: 'text-blue-400' },
        aerial: { health: 30, speed: 2.9, reward: 15, icon: 'fa-plane', color: 'text-cyan-400', flying: true },
        boss: { health: 800, speed: 0.8, reward: 50, icon: 'fa-dragon', color: 'text-purple-400', size: 2 },
        airboss: { health: 1000, speed: 2.2, reward: 100, icon: 'fa-plane-departure', color: 'text-indigo-400', size: 2.5, flying: true },
        panda: { health: 1000000, speed: 0.4, reward: 5000, icon: 'fa-paw', color: 'text-pink-400', size: 3, boss: true }
    };

    const ABILITIES = {
        airstrike: { cost: 150, cooldown: 45000, damage: 150, radius: 100 },
        freeze: { cost: 100, cooldown: 30000, duration: 5000 },
        boost: { cost: 120, cooldown: 60000, duration: 10000, multiplier: 2 },
        reinforcement: { cost: 200, cooldown: 90000, duration: 15000 },
        nuke: { cost: 1000, cooldown: 120000, damage: 3000, radius: 200, pandaDamage: 1000 }
    };

    // Variables du jeu
    let gameState = {
        wave: 1,
        money: 600,
        baseHealth: 100,
        maxBaseHealth: 100,
        score: 0,
        selectedTower: null,
        towers: [],
        enemies: [],
        projectiles: [],
        kills: 0,
        waveActive: false,
        path: [],
        difficulty: 'medium',
        maxWaves: 25,
        abilityCooldowns: {},
        reinforcements: [],
        boostedTowers: new Set(),
        godMode: false,
        moneyGenerators: []
    };

    let gameLoop = null;
    let lastUpdate = Date.now();
    let moneyGenTimer = Date.now();

    // Afficher le panel admin si Enoe
    if (isEnoe) {
        document.getElementById('admin-panel').classList.remove('hidden');
    }

    function toggleAdminPanel() {
        const panel = document.getElementById('admin-panel');
        panel.classList.toggle('hidden');
    }

    // === FONCTIONS ADMIN ===
    function adminAddMoney() {
        if (!isEnoe) return;
        gameState.money += 1000;
        gameState.score += 500;
        updateHUD();
        showNotification('💰 +1000 Argent ajouté !', 'success');
    }

    function adminSkipWave() {
        if (!isEnoe) return;
        
        gameState.enemies.forEach(enemy => {
            if (enemy.health > 0) {
                killEnemy(enemy.id, false);
            }
        });
        
        gameState.waveActive = false;
        gameState.wave++;
        gameState.money += 200;
        gameState.score += 1000;
        
        document.getElementById('start-wave-btn').disabled = false;
        document.getElementById('start-wave-btn').classList.remove('opacity-50');
        
        if (gameState.wave > gameState.maxWaves) {
            endGame(true);
        } else {
            updateHUD();
            showNotification('⏩ Vague skippée !', 'success');
        }
    }

    function adminKillAll() {
        if (!isEnoe) return;
        
        let killCount = 0;
        gameState.enemies.forEach(enemy => {
            if (enemy.health > 0) {
                killEnemy(enemy.id, true);
                killCount++;
            }
        });
        
        showNotification(`💀 ${killCount} ennemis éliminés !`, 'success');
    }

    function adminHealBase() {
        if (!isEnoe) return;
        gameState.baseHealth = gameState.maxBaseHealth;
        updateHUD();
        showNotification('❤️ Base complètement soignée !', 'success');
    }

    function adminGodMode() {
        if (!isEnoe) return;
        gameState.godMode = !gameState.godMode;
        
        const btn = document.getElementById('god-mode-btn');
        if (gameState.godMode) {
            btn.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>God Mode: ON';
            btn.classList.remove('bg-yellow-600', 'hover:bg-yellow-700');
            btn.classList.add('bg-green-600', 'hover:bg-green-700');
            showNotification('🛡️ God Mode ACTIVÉ !', 'success');
        } else {
            btn.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>God Mode: OFF';
            btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            btn.classList.add('bg-yellow-600', 'hover:bg-yellow-700');
            showNotification('🛡️ God Mode DÉSACTIVÉ', 'info');
        }
    }

    function adminSpawnEnemy(type) {
        if (!isEnoe) return;
        spawnEnemy(gameState.wave, type);
        showNotification(`👾 ${type.toUpperCase()} spawné !`, 'success');
    }

    function adminSpawnWave() {
        if (!isEnoe) return;
        for (let i = 0; i < 10; i++) {
            setTimeout(() => spawnEnemy(gameState.wave), i * 500);
        }
        showNotification('🌊 Vague de 10 ennemis spawnée !', 'success');
    }

    function adminUseAbility(abilityType) {
        if (!isEnoe) return;
        
        const now = Date.now();
        gameState.abilityCooldowns[abilityType] = 0; // Reset cooldown
        
        if (abilityType === 'airstrike') {
            executeAirstrike();
        } else if (abilityType === 'freeze') {
            executeFreeze(now);
        } else if (abilityType === 'boost') {
            executeBoost(now);
        } else if (abilityType === 'reinforcement') {
            executeReinforcement(now);
        }
        
        showNotification(`✨ Capacité ${abilityType} activée !`, 'success');
    }

    function showNotification(message, type = 'info') {
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600',
            warning: 'bg-yellow-600'
        };
        
        const notif = document.createElement('div');
        notif.className = `fixed top-24 right-24 ${colors[type]} text-white px-6 py-4 rounded-lg shadow-2xl z-50 font-bold`;
        notif.textContent = message;
        notif.style.animation = 'slideDown 0.5s ease-out';
        
        document.body.appendChild(notif);
        
        setTimeout(() => {
            notif.style.opacity = '0';
            notif.style.transition = 'opacity 0.5s';
            setTimeout(() => notif.remove(), 500);
        }, 2000);
    }

    const originalStartGameLogic = function(difficulty) {
        gameState.difficulty = difficulty;
        if (difficulty === 'easy') {
            gameState.money = 10000;
            gameState.baseHealth = 200;
            gameState.maxBaseHealth = 200;
            gameState.maxWaves = 10;
        } else if (difficulty === 'medium') {
            gameState.money = 600;
            gameState.baseHealth = 100;
            gameState.maxBaseHealth = 100;
            gameState.maxWaves = 15;
        } else if (difficulty === 'hard') {
            gameState.money = 100;
            gameState.baseHealth = 25;
            gameState.maxBaseHealth = 25;
            gameState.maxWaves = 20;
        }
        
        document.getElementById('start-screen').classList.add('hidden');
        document.getElementById('game-area').classList.remove('hidden');
        
        initializeGrid();
        generatePath();
        updateHUD();

        gameLoop = setInterval(update, 50);
    };

    function startGame(difficulty) {
        originalStartGameLogic(difficulty);
        startGameSession();
    }

    function initializeGrid() {
        const grid = document.getElementById('game-grid');
        grid.innerHTML = '';
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = `repeat(${GRID_SIZE}, ${CELL_SIZE}px)`;
        grid.style.gridTemplateRows = `repeat(${GRID_SIZE}, ${CELL_SIZE}px)`;
        
        for (let y = 0; y < GRID_SIZE; y++) {
            for (let x = 0; x < GRID_SIZE; x++) {
                const cell = document.createElement('div');
                cell.className = 'game-cell';
                cell.dataset.x = x;
                cell.dataset.y = y;
                cell.style.width = CELL_SIZE + 'px';
                cell.style.height = CELL_SIZE + 'px';
                cell.onclick = () => placeTower(x, y);
                grid.appendChild(cell);
            }
        }
    }

    function generatePath() {
        gameState.path = [];
        let x = 0, y = Math.floor(GRID_SIZE / 2);
        
        while (x < GRID_SIZE) {
            gameState.path.push({ x, y });
            
            const cell = document.querySelector(`[data-x="${x}"][data-y="${y}"]`);
            if (cell) cell.classList.add('path');
            
            if (Math.random() < 0.15 && x < GRID_SIZE - 5 && x > 2) {
                const direction = Math.random() < 0.5 ? -1 : 1;
                if (y + direction >= 2 && y + direction < GRID_SIZE - 2) {
                    for (let i = 0; i < 3; i++) {
                        y += direction;
                        gameState.path.push({ x, y });
                        const turnCell = document.querySelector(`[data-x="${x}"][data-y="${y}"]`);
                        if (turnCell) turnCell.classList.add('path');
                    }
                }
            }
            x++;
        }
        
        const baseCell = document.querySelector(`[data-x="${GRID_SIZE-1}"][data-y="${y}"]`);
        if (baseCell) {
            baseCell.classList.add('base');
            baseCell.innerHTML = '<i class="fas fa-flag text-green-500 text-2xl"></i>';
        }
    }

    function selectTower(type) {
        document.querySelectorAll('.tower-menu').forEach(btn => {
            btn.classList.remove('border-blue-500');
        });
        
        const btn = document.getElementById(`tower-${type}`);
        if (btn) {
            btn.classList.add('border-blue-500');
            gameState.selectedTower = type;
        }
    }

    function placeTower(x, y) {
        if (!gameState.selectedTower) {
            showNotification('Sélectionnez d\'abord un type de tour !', 'warning');
            return;
        }
        
        const towerType = TOWER_TYPES[gameState.selectedTower];
        
        if (gameState.money < towerType.cost) {
            showNotification('Pas assez d\'argent !', 'error');
            return;
        }
        
        const cell = document.querySelector(`[data-x="${x}"][data-y="${y}"]`);
        if (cell.classList.contains('path') || cell.querySelector('.tower')) {
            showNotification('Impossible de placer une tour ici !', 'error');
            return;
        }
        
        gameState.money -= towerType.cost;
        
        const tower = {
            x, y,
            type: gameState.selectedTower,
            ...towerType,
            lastFire: 0,
            id: Date.now() + Math.random()
        };
        
        gameState.towers.push(tower);
        
        if (tower.moneyGen) {
            gameState.moneyGenerators.push(tower);
        }
        
        const towerEl = document.createElement('div');
        towerEl.className = `tower absolute ${towerType.color}`;
        if (tower.moneyGen) {
            towerEl.classList.add('generator');
        }
        towerEl.style.left = x * CELL_SIZE + 'px';
        towerEl.style.top = y * CELL_SIZE + 'px';
        towerEl.style.width = CELL_SIZE + 'px';
        towerEl.style.height = CELL_SIZE + 'px';
        towerEl.style.display = 'flex';
        towerEl.style.alignItems = 'center';
        towerEl.style.justifyContent = 'center';
        towerEl.style.zIndex = '5';
        towerEl.innerHTML = `<i class="fas ${towerType.icon} text-2xl"></i>`;
        towerEl.dataset.towerId = tower.id;
        
        document.getElementById('game-grid').appendChild(towerEl);
        
        updateHUD();
    }

    function startWave() {
        if (gameState.waveActive) return;
        
        gameState.waveActive = true;
        document.getElementById('start-wave-btn').disabled = true;
        document.getElementById('start-wave-btn').classList.add('opacity-50');
        
        const wave = gameState.wave;
        let enemyCount = 8 + (wave * 3);
        
        if (gameState.difficulty === 'hard') {
            enemyCount = Math.floor(enemyCount * 1.5);
        }
        
        document.getElementById('wave-number').textContent = wave;
        document.getElementById('enemy-count').textContent = enemyCount;
        
        const isBossWave = wave % 5 === 0;
        const isAirBossWave = wave % 7 === 0 && wave > 10;
        const isFinalBoss = wave === gameState.maxWaves && gameState.difficulty === 'hard';
        
        if (isBossWave || isFinalBoss || isAirBossWave) {
            document.getElementById('boss-warning').classList.remove('hidden');
        } else {
            document.getElementById('boss-warning').classList.add('hidden');
        }
        
        if (gameState.difficulty === 'hard' && wave > 20) {
            document.getElementById('difficulty-warning').classList.remove('hidden');
            document.getElementById('difficulty-warning').textContent = '💀 HARDCORE MODE - Ennemis ultra renforcés !';
        } else {
            document.getElementById('difficulty-warning').classList.add('hidden');
        }
        
        document.getElementById('wave-alert').classList.remove('hidden');
        
        setTimeout(() => {
            document.getElementById('wave-alert').classList.add('hidden');
        }, 2500);
        
        for (let i = 0; i < enemyCount; i++) {
            setTimeout(() => spawnEnemy(wave), i * 700);
        }
        
        if (isFinalBoss) {
            setTimeout(() => spawnEnemy(wave, 'panda'), enemyCount * 700 + 2000);
        } else if (isAirBossWave) {
            setTimeout(() => spawnEnemy(wave, 'airboss'), enemyCount * 700 + 1500);
        } else if (isBossWave) {
            const bossCount = gameState.difficulty === 'hard' ? 2 : 1;
            for (let i = 0; i < bossCount; i++) {
                setTimeout(() => spawnEnemy(wave, 'boss'), enemyCount * 700 + 1000 + (i * 1500));
            }
        }
    }

    function spawnEnemy(wave, forceType = null) {
        const startPos = gameState.path[0];
        
        let type;
        if (forceType) {
            type = forceType;
        } else {
            const rand = Math.random();
            if (wave < 5) {
                type = rand < 0.6 ? 'normal' : (rand < 0.85 ? 'runner' : 'tank');
            } else if (wave < 10) {
                type = rand < 0.3 ? 'normal' : (rand < 0.5 ? 'runner' : (rand < 0.7 ? 'tank' : 'aerial'));
            } else if (wave < 20) {
                type = rand < 0.2 ? 'normal' : (rand < 0.35 ? 'runner' : (rand < 0.55 ? 'tank' : (rand < 0.75 ? 'aerial' : 'boss')));
            } else {
                type = rand < 0.1 ? 'normal' : (rand < 0.2 ? 'runner' : (rand < 0.4 ? 'tank' : (rand < 0.6 ? 'aerial' : (rand < 0.85 ? 'boss' : 'airboss'))));
            }
        }
        
        const enemyTemplate = ENEMY_TYPES[type];
        let healthMultiplier = 1 + (wave * 0.15);
        let speedMultiplier = 1;
        
        if (gameState.difficulty === 'hard') {
            healthMultiplier *= 2;
            speedMultiplier = 1.3;
            
            if (wave > 20) {
                healthMultiplier *= 1.5;
                speedMultiplier = 1.5;
            }
        }
        
        const enemy = {
            x: startPos.x * CELL_SIZE + CELL_SIZE / 2,
            y: startPos.y * CELL_SIZE + CELL_SIZE / 2,
            pathIndex: 0,
            health: enemyTemplate.health * healthMultiplier,
            maxHealth: enemyTemplate.health * healthMultiplier,
            speed: enemyTemplate.speed * speedMultiplier,
            reward: Math.floor(enemyTemplate.reward * (1 + wave * 0.1)),
            type: type,
            icon: enemyTemplate.icon,
            color: enemyTemplate.color,
            flying: enemyTemplate.flying || false,
            size: enemyTemplate.size || 1,
            frozen: false,
            frozenUntil: 0,
            id: Date.now() + Math.random()
        };
        
        gameState.enemies.push(enemy);
        
        const enemyEl = document.createElement('div');
        enemyEl.className = `enemy-unit absolute ${enemy.color}`;
        if (enemy.type === 'boss' || enemy.type === 'panda' || enemy.type === 'airboss') {
            enemyEl.classList.add('boss');
        }
        enemyEl.style.left = (enemy.x - 15) + 'px';
        enemyEl.style.top = (enemy.y - 15) + 'px';
        enemyEl.style.zIndex = enemy.flying ? '8' : '3';
        const iconSize = enemy.size > 1 ? (enemy.size * 1.5) + 'em' : '1.5em';
        enemyEl.innerHTML = `
            <div class="enemy-health-bar">
                <div class="enemy-health-fill" style="width: 100%"></div>
            </div>
            <i class="fas ${enemy.icon}" style="font-size: ${iconSize}"></i>
        `;
        enemyEl.dataset.enemyId = enemy.id;
        
        document.getElementById('game-grid').appendChild(enemyEl);
    }

    function update() {
        const now = Date.now();
        
        // Money generators
        if (now - moneyGenTimer > 1000) {
            gameState.moneyGenerators.forEach(gen => {
                gameState.money += gen.moneyGen;
                gameState.score += gen.moneyGen * 2;
            });
            moneyGenTimer = now;
        }
        
        updateEnemies(now);
        updateTowers(now);
        updateProjectiles();
        updateReinforcements(now);
        updateAbilityCooldowns(now);
        updateAdminStats();
    }

    function updateAdminStats() {
        if (!isEnoe) return;
        
        const aliveEnemies = gameState.enemies.filter(e => e.health > 0).length;
        document.getElementById('admin-enemy-count').textContent = aliveEnemies;
        document.getElementById('admin-tower-count').textContent = gameState.towers.length;
        document.getElementById('admin-projectile-count').textContent = gameState.projectiles.length;
    }

    function updateEnemies(now) {
        let allDead = true;
        
        gameState.enemies.forEach((enemy) => {
            if (enemy.health <= 0) return;
            
            allDead = false;
            
            if (enemy.frozen && now < enemy.frozenUntil) {
                const enemyEl = document.querySelector(`[data-enemy-id="${enemy.id}"]`);
                if (enemyEl && !enemyEl.classList.contains('frozen')) {
                    enemyEl.classList.add('frozen');
                }
                return;
            } else if (enemy.frozen && now >= enemy.frozenUntil) {
                enemy.frozen = false;
                const enemyEl = document.querySelector(`[data-enemy-id="${enemy.id}"]`);
                if (enemyEl) enemyEl.classList.remove('frozen');
            }
            
            if (enemy.pathIndex < gameState.path.length - 1) {
                const target = gameState.path[enemy.pathIndex + 1];
                const targetX = target.x * CELL_SIZE + CELL_SIZE / 2;
                const targetY = target.y * CELL_SIZE + CELL_SIZE / 2;
                
                const dx = targetX - enemy.x;
                const dy = targetY - enemy.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < enemy.speed) {
                    enemy.pathIndex++;
                } else {
                    enemy.x += (dx / distance) * enemy.speed;
                    enemy.y += (dy / distance) * enemy.speed;
                }
                
                const enemyEl = document.querySelector(`[data-enemy-id="${enemy.id}"]`);
                if (enemyEl) {
                    enemyEl.style.left = (enemy.x - 15) + 'px';
                    enemyEl.style.top = (enemy.y - 15) + 'px';
                    
                    const healthBar = enemyEl.querySelector('.enemy-health-fill');
                    if (healthBar) {
                        const healthPercent = (enemy.health / enemy.maxHealth) * 100;
                        healthBar.style.width = healthPercent + '%';
                    }
                }
            } else {
                // ENNEMI A ATTEINT LA BASE - APPLIQUER LES DÉGÂTS
                if (!gameState.godMode) {
                    let damage = 10;
                    if (enemy.type === 'boss') damage = 20;
                    if (enemy.type === 'airboss') damage = 25;
                    if (enemy.type === 'panda') damage = 30;
                    if (gameState.difficulty === 'hard') damage *= 1.5;
                    
                    gameState.baseHealth -= damage;
                    
                    if (gameState.baseHealth <= 0) {
                        gameState.baseHealth = 0;
                        updateHUD();
                        killEnemy(enemy.id, false);
                        endGame(false);
                        return;
                    }
                }
                
                killEnemy(enemy.id, false);
            }
        });
        
        if (gameState.waveActive && allDead) {
            gameState.waveActive = false;
            gameState.wave++;
            const bonus = 80 + (gameState.wave * 10);
            gameState.money += bonus;
            gameState.score += bonus * 5;
            
            document.getElementById('start-wave-btn').disabled = false;
            document.getElementById('start-wave-btn').classList.remove('opacity-50');
            
            if (gameState.wave > gameState.maxWaves) {
                endGame(true);
            } else {
                showNotification(`✅ Vague ${gameState.wave - 1} terminée ! +${bonus}💰`, 'success');
            }
        }
        
        updateHUD();
    }

    function updateTowers(now) {
        gameState.towers.forEach(tower => {
            if (tower.moneyGen) return; // Skip generators
            
            const isBoosted = gameState.boostedTowers.has(tower.id);
            const fireRate = isBoosted ? tower.fireRate / 2 : tower.fireRate;
            
            if (now - tower.lastFire < fireRate) return;
            
            let target = null;
            let minDist = Infinity;
            
            gameState.enemies.forEach((enemy) => {
                if (enemy.health <= 0) return;
                
                if (tower.airOnly && !enemy.flying) return;
                if (!tower.airOnly && !tower.airCapable && enemy.flying) return;
                
                const dx = enemy.x - (tower.x * CELL_SIZE + CELL_SIZE / 2);
                const dy = enemy.y - (tower.y * CELL_SIZE + CELL_SIZE / 2);
                const dist = Math.sqrt(dx * dx + dy * dy);
                
                if (dist < tower.range && dist < minDist) {
                    minDist = dist;
                    target = enemy;
                }
            });
            
            if (target) {
                tower.lastFire = now;
                const damage = isBoosted ? tower.damage * 2 : tower.damage;
                fireProjectile(tower, target, damage);
            }
        });
    }

    function fireProjectile(tower, enemy, damage) {
        const projectile = {
            x: tower.x * CELL_SIZE + CELL_SIZE / 2,
            y: tower.y * CELL_SIZE + CELL_SIZE / 2,
            targetX: enemy.x,
            targetY: enemy.y,
            damage: damage,
            speed: 7,
            enemyId: enemy.id,
            splash: tower.splash || 0,
            color: tower.projectileColor,
            id: Date.now() + Math.random()
        };
        
        gameState.projectiles.push(projectile);
        
        const projEl = document.createElement('div');
        projEl.className = 'projectile';
        projEl.style.left = projectile.x + 'px';
        projEl.style.top = projectile.y + 'px';
        projEl.style.background = projectile.color;
        projEl.style.boxShadow = `0 0 15px ${projectile.color}`;
        projEl.dataset.projId = projectile.id;
        
        document.getElementById('game-grid').appendChild(projEl);
    }

    function updateProjectiles() {
        gameState.projectiles = gameState.projectiles.filter(proj => {
            const dx = proj.targetX - proj.x;
            const dy = proj.targetY - proj.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            
            if (distance < proj.speed) {
                hitEnemy(proj.enemyId, proj.damage, proj.splash, proj.targetX, proj.targetY);
                
                const projEl = document.querySelector(`[data-proj-id="${proj.id}"]`);
                if (projEl) projEl.remove();
                return false;
            } else {
                proj.x += (dx / distance) * proj.speed;
                proj.y += (dy / distance) * proj.speed;
                
                const projEl = document.querySelector(`[data-proj-id="${proj.id}"]`);
                if (projEl) {
                    projEl.style.left = proj.x + 'px';
                    projEl.style.top = proj.y + 'px';
                }
                return true;
            }
        });
    }

    function hitEnemy(enemyId, damage, splash, x, y) {
        if (splash > 0) {
            let hitCount = 0;
            gameState.enemies.forEach((enemy) => {
                if (enemy.health <= 0) return;
                
                const dx = enemy.x - x;
                const dy = enemy.y - y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                
                if (dist < splash) {
                    enemy.health -= damage;
                    hitCount++;
                    if (enemy.health <= 0) {
                        killEnemy(enemy.id, true);
                    }
                }
            });
            
            if (hitCount > 0) {
                createExplosion(x, y, true);
            }
        } else {
            const enemy = gameState.enemies.find(e => e.id === enemyId);
            if (enemy && enemy.health > 0) {
                enemy.health -= damage;
                if (enemy.health <= 0) {
                    killEnemy(enemy.id, true);
                }
            }
        }
    }

    function killEnemy(enemyId, giveReward) {
        const enemyIndex = gameState.enemies.findIndex(e => e.id === enemyId);
        if (enemyIndex === -1) return;
        
        const enemy = gameState.enemies[enemyIndex];
        
        if (giveReward && enemy.health <= 0) {
            gameState.money += enemy.reward;
            gameState.score += enemy.reward * 10;
            gameState.kills++;
        }
        
        enemy.health = 0;
        
        const enemyEl = document.querySelector(`[data-enemy-id="${enemyId}"]`);
        if (enemyEl) {
            createExplosion(enemy.x, enemy.y, false);
            enemyEl.remove();
        }
    }

    function createExplosion(x, y, isSplash) {
        const explosion = document.createElement('div');
        explosion.className = 'explosion absolute text-4xl text-orange-500 pointer-events-none';
        explosion.innerHTML = isSplash ? '<i class="fas fa-burst"></i>' : '<i class="fas fa-fire"></i>';
        explosion.style.left = x + 'px';
        explosion.style.top = y + 'px';
        explosion.style.transform = 'translate(-50%, -50%)';
        
        document.getElementById('game-grid').appendChild(explosion);
        setTimeout(() => explosion.remove(), 500);
    }

    function useAbility(abilityType) {
        const ability = ABILITIES[abilityType];
        const now = Date.now();
        
        if (gameState.abilityCooldowns[abilityType] && now < gameState.abilityCooldowns[abilityType]) {
            showNotification('Capacité en cooldown !', 'warning');
            return;
        }
        
        if (gameState.money < ability.cost) {
            showNotification('Pas assez d\'argent !', 'error');
            return;
        }
        
        gameState.money -= ability.cost;
        gameState.abilityCooldowns[abilityType] = now + ability.cooldown;
        
        if (abilityType === 'airstrike') {
            executeAirstrike();
        } else if (abilityType === 'freeze') {
            executeFreeze(now);
        } else if (abilityType === 'boost') {
            executeBoost(now);
        } else if (abilityType === 'reinforcement') {
            executeReinforcement(now);
        } else if (abilityType === 'nuke') {
            executeNuke();
        }
        
        updateHUD();
    }

    function executeAirstrike() {
        const targetPath = gameState.path[Math.floor(gameState.path.length / 2)];
        const targetX = targetPath.x * CELL_SIZE + CELL_SIZE / 2;
        const targetY = targetPath.y * CELL_SIZE + CELL_SIZE / 2;
        
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const offsetX = (Math.random() - 0.5) * 100;
                const offsetY = (Math.random() - 0.5) * 100;
                
                gameState.enemies.forEach(enemy => {
                    if (enemy.health <= 0) return;
                    
                    const dx = enemy.x - (targetX + offsetX);
                    const dy = enemy.y - (targetY + offsetY);
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    
                    if (dist < ABILITIES.airstrike.radius) {
                        enemy.health -= ABILITIES.airstrike.damage;
                        if (enemy.health <= 0) {
                            killEnemy(enemy.id, true);
                        }
                    }
                });
                
                createExplosion(targetX + offsetX, targetY + offsetY, true);
            }, i * 300);
        }
        
        showNotification('💣 Frappe aérienne lancée !', 'success');
    }

    function executeFreeze(now) {
        let frozenCount = 0;
        gameState.enemies.forEach(enemy => {
            if (enemy.health > 0 && !enemy.frozen) {
                enemy.frozen = true;
                enemy.frozenUntil = now + ABILITIES.freeze.duration;
                frozenCount++;
            }
        });
        showNotification(`❄️ ${frozenCount} ennemis gelés !`, 'success');
    }

    function executeBoost(now) {
        gameState.boostedTowers.clear();
        gameState.towers.forEach(tower => {
            gameState.boostedTowers.add(tower.id);
            const towerEl = document.querySelector(`[data-tower-id="${tower.id}"]`);
            if (towerEl) towerEl.classList.add('boosted');
        });
        
        setTimeout(() => {
            gameState.boostedTowers.clear();
            document.querySelectorAll('.tower').forEach(el => el.classList.remove('boosted'));
        }, ABILITIES.boost.duration);
        
        showNotification('⚡ Tours boostées x2 pendant 10s !', 'success');
    }

    function executeReinforcement(now) {
        const endPath = gameState.path[gameState.path.length - 1];
        
        for (let i = 0; i < 5; i++) {
            setTimeout(() => {
                const reinforcement = {
                    x: endPath.x * CELL_SIZE + CELL_SIZE / 2,
                    y: endPath.y * CELL_SIZE + CELL_SIZE / 2,
                    pathIndex: gameState.path.length - 1,
                    damage: 30,
                    expiresAt: now + ABILITIES.reinforcement.duration + (i * 500),
                    id: Date.now() + Math.random()
                };
                
                gameState.reinforcements.push(reinforcement);
                
                const reinforcementEl = document.createElement('div');
                reinforcementEl.className = 'enemy-unit absolute text-green-400';
                reinforcementEl.style.left = (reinforcement.x - 15) + 'px';
                reinforcementEl.style.top = (reinforcement.y - 15) + 'px';
                reinforcementEl.style.zIndex = '3';
                reinforcementEl.innerHTML = '<i class="fas fa-helicopter text-2xl"></i>';
                reinforcementEl.dataset.reinforcementId = reinforcement.id;
                
                document.getElementById('game-grid').appendChild(reinforcementEl);
            }, i * 500);
        }
        
        showNotification('🚁 Renforts déployés !', 'success');
    }

    function executeNuke() {
        const flash = document.createElement('div');
        flash.className = 'fixed inset-0 bg-white z-50';
        flash.style.animation = 'explosion 1s ease-out';
        document.body.appendChild(flash);
        setTimeout(() => flash.remove(), 1000);
        
        let killCount = 0;
        gameState.enemies.forEach(enemy => {
            if (enemy.health <= 0) return;
            
            if (enemy.type === 'panda') {
                enemy.health -= ABILITIES.nuke.pandaDamage;
                showNotification(`🐼 Panda: -${ABILITIES.nuke.pandaDamage.toLocaleString()} PV !`, 'warning');
            } else {
                enemy.health -= ABILITIES.nuke.damage;
            }
            
            if (enemy.health <= 0) {
                killEnemy(enemy.id, true);
                killCount++;
            }
        });
        
        for (let i = 0; i < 20; i++) {
            setTimeout(() => {
                const x = Math.random() * GRID_SIZE * CELL_SIZE;
                const y = Math.random() * GRID_SIZE * CELL_SIZE;
                createExplosion(x, y, true);
            }, i * 50);
        }
        
        showNotification(`☢️ BOMBE NUCLÉAIRE ! ${killCount} ennemis éliminés !`, 'success');
    }

    function updateReinforcements(now) {
        gameState.reinforcements = gameState.reinforcements.filter(reinforcement => {
            if (now > reinforcement.expiresAt) {
                const el = document.querySelector(`[data-reinforcement-id="${reinforcement.id}"]`);
                if (el) el.remove();
                return false;
            }
            
            if (reinforcement.pathIndex > 0) {
                const target = gameState.path[reinforcement.pathIndex - 1];
                const targetX = target.x * CELL_SIZE + CELL_SIZE / 2;
                const targetY = target.y * CELL_SIZE + CELL_SIZE / 2;
                
                const dx = targetX - reinforcement.x;
                const dy = targetY - reinforcement.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < 2) {
                    reinforcement.pathIndex--;
                } else {
                    reinforcement.x += (dx / distance) * 2;
                    reinforcement.y += (dy / distance) * 2;
                }
                
                const el = document.querySelector(`[data-reinforcement-id="${reinforcement.id}"]`);
                if (el) {
                    el.style.left = (reinforcement.x - 15) + 'px';
                    el.style.top = (reinforcement.y - 15) + 'px';
                }
                
                gameState.enemies.forEach(enemy => {
                    if (enemy.health <= 0) return;
                    
                    const dx = enemy.x - reinforcement.x;
                    const dy = enemy.y - reinforcement.y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    
                    if (dist < 30) {
                        enemy.health -= reinforcement.damage;
                        if (enemy.health <= 0) {
                            killEnemy(enemy.id, true);
                        }
                    }
                });
            }
            
            return true;
        });
    }

    function updateAbilityCooldowns(now) {
        Object.keys(ABILITIES).forEach(abilityType => {
            const btn = document.getElementById(`ability-${abilityType}`);
            if (!btn) return;
            
            const cooldownEnd = gameState.abilityCooldowns[abilityType];
            
            let existingCooldown = btn.querySelector('.ability-cooldown');
            
            if (cooldownEnd && now < cooldownEnd) {
                const remaining = Math.ceil((cooldownEnd - now) / 1000);
                
                if (!existingCooldown) {
                    existingCooldown = document.createElement('div');
                    existingCooldown.className = 'ability-cooldown';
                    btn.appendChild(existingCooldown);
                }
                
                existingCooldown.textContent = remaining + 's';
                btn.disabled = true;
                btn.classList.add('opacity-50');
            } else {
                if (existingCooldown) {
                    existingCooldown.remove();
                }
                btn.disabled = false;
                btn.classList.remove('opacity-50');
            }
        });
    }

    function updateHUD() {
        document.getElementById('wave').textContent = gameState.wave;
        document.getElementById('max-waves').textContent = gameState.maxWaves;
        document.getElementById('money').textContent = gameState.money;
        document.getElementById('score').textContent = gameState.score;
        document.getElementById('base-health-text').textContent = Math.max(0, Math.floor(gameState.baseHealth));
        document.getElementById('max-health-text').textContent = gameState.maxBaseHealth;
        
        const healthPercent = Math.max(0, (gameState.baseHealth / gameState.maxBaseHealth) * 100);
        const healthBar = document.getElementById('base-health');
        healthBar.style.width = healthPercent + '%';
        
        if (healthPercent < 30) {
            healthBar.className = 'health-bar h-full bg-gradient-to-r from-red-500 to-red-400';
        } else if (healthPercent < 60) {
            healthBar.className = 'health-bar h-full bg-gradient-to-r from-yellow-500 to-yellow-400';
        } else {
            healthBar.className = 'health-bar h-full bg-gradient-to-r from-green-500 to-green-400';
        }
    }

    function endGame(victory) {
        console.log('🎮 endGame appelé, victory:', victory);
        
        if (gameLoop) {
            clearInterval(gameLoop);
            gameLoop = null;
        }
        
        if (syncInterval) {
            clearInterval(syncInterval);
            syncInterval = null;
        }

        if (commandInterval) {
            clearInterval(commandInterval);
            commandInterval = null;
        }
        
        // Marquer la session comme terminée
        syncGameState(true);
        
        const gameArea = document.getElementById('game-area');
        const gameOverScreen = document.getElementById('game-over-screen');
        
        if (gameArea) {
            gameArea.style.display = 'none';
            gameArea.classList.add('hidden');
        }
        
        if (gameOverScreen) {
            gameOverScreen.style.display = 'block';
            gameOverScreen.classList.remove('hidden');
        }
        
        if (victory) {
            const icon = document.getElementById('game-over-icon');
            const title = document.getElementById('game-over-title');
            if (icon) icon.className = 'fas fa-trophy text-yellow-500 text-8xl mb-6';
            if (title) {
                title.textContent = gameState.difficulty === 'hard'
                    ? '💀 VICTOIRE LÉGENDAIRE ! 💀'
                    : 'Victoire !';
            }
        } else {
            const icon = document.getElementById('game-over-icon');
            const title = document.getElementById('game-over-title');
            if (icon) icon.className = 'fas fa-skull-crossbones text-red-500 text-8xl mb-6';
            if (title) title.textContent = 'Mission Échouée';
        }
        
        const finalScore  = document.getElementById('final-score');
        const finalWave   = document.getElementById('final-wave');
        const totalKills  = document.getElementById('total-kills');
        const totalTowers = document.getElementById('total-towers');
        
        if (finalScore)  finalScore.textContent  = gameState.score;
        if (finalWave)   finalWave.textContent    = gameState.wave;
        if (totalKills)  totalKills.textContent   = gameState.kills;
        if (totalTowers) totalTowers.textContent  = gameState.towers.length;
    }

    function restartGame() {
        const difficulty = gameState.difficulty;
        
        gameState = {
            wave: 1,
            money: 600,
            baseHealth: 100,
            maxBaseHealth: 100,
            score: 0,
            selectedTower: null,
            towers: [],
            enemies: [],
            projectiles: [],
            kills: 0,
            waveActive: false,
            path: [],
            difficulty: difficulty,
            maxWaves: 10,
            abilityCooldowns: {},
            reinforcements: [],
            boostedTowers: new Set(),
            godMode: false,
            moneyGenerators: []
        };
        
        lastUpdate = Date.now();
        moneyGenTimer = Date.now();
        
        document.getElementById('game-over-screen').classList.add('hidden');
        
        startGame(difficulty);
    }

    function backToStart() {
        gameState = {
            wave: 1,
            money: 600,
            baseHealth: 100,
            maxBaseHealth: 100,
            score: 0,
            selectedTower: null,
            towers: [],
            enemies: [],
            projectiles: [],
            kills: 0,
            waveActive: false,
            path: [],
            difficulty: 'medium',
            maxWaves: 10,
            abilityCooldowns: {},
            reinforcements: [],
            boostedTowers: new Set(),
            godMode: false,
            moneyGenerators: []
        };
        
        clearInterval(gameLoop);
        
        document.getElementById('game-over-screen').classList.add('hidden');
        document.getElementById('game-area').classList.add('hidden');
        document.getElementById('start-screen').classList.remove('hidden');
        
        document.getElementById('game-grid').innerHTML = '';
    }

    // ════════════════════════════════════════════════════════════
    //  SYNCHRONISATION SESSION — version fusionnée (doc 1)
    // ════════════════════════════════════════════════════════════

    // Variables de sync
    let syncInterval    = null;
    let commandInterval = null;

    // Démarrage de la session
    function startGameSession() {
        syncGameState();                                        // première sync immédiate
        syncInterval    = setInterval(syncGameState, 3000);    // puis toutes les 3s
        commandInterval = setInterval(checkAdminCommands, 2000); // commandes admin toutes les 2s

        // Marquer inactif à la fermeture
        window.addEventListener('beforeunload', () => syncGameState(true));
    }

    // Envoi de l'état du jeu vers la BDD
    function syncGameState(isClosing = false) {
        const aliveEnemies = gameState.enemies.filter(e => e.health > 0).length;

        const fd = new FormData();
        fd.append('action',           'sync_session');
        fd.append('session_id',       gameSessionId);
        fd.append('player_name',      playerName);
        fd.append('difficulty',       gameState.difficulty);
        fd.append('wave',             gameState.wave);
        fd.append('money',            gameState.money);
        fd.append('base_health',      Math.max(0, Math.floor(gameState.baseHealth)));
        fd.append('max_base_health',  gameState.maxBaseHealth);
        fd.append('score',            gameState.score);
        fd.append('kills',            gameState.kills);
        fd.append('towers_count',     gameState.towers.length);
        fd.append('enemies_alive',    aliveEnemies);
        fd.append('is_active',        isClosing ? '0' : '1');

        fetch('sync_game_session.php', { method: 'POST', body: fd })
            .catch(err => console.warn('Sync error:', err));
    }

    // Réception et exécution des commandes admin
    async function checkAdminCommands() {
        try {
            const res  = await fetch(`sync_game_session.php?action=get_commands&session_id=${gameSessionId}&_=${Date.now()}`);
            const data = await res.json();

            if (!data.success || !data.commands || data.commands.length === 0) return;

            data.commands.forEach(cmd => {
                switch (cmd.type) {

                    case 'add_money':
                        gameState.money += parseInt(cmd.amount ?? 1000);
                        updateHUD();
                        showNotification(`💰 +${cmd.amount} ajouté par l'admin !`, 'success');
                        break;

                    case 'spawn_enemy':
                        spawnEnemy(gameState.wave, cmd.enemy_type ?? 'normal');
                        showNotification(`👾 L'admin a spawné un ${cmd.enemy_type} !`, 'info');
                        break;

                    case 'kill_all': {
                        let n = 0;
                        gameState.enemies.forEach(e => { if (e.health > 0) { killEnemy(e.id, true); n++; } });
                        showNotification(`💀 Admin : ${n} ennemis éliminés !`, 'info');
                        break;
                    }

                    case 'heal_base':
                        gameState.baseHealth = gameState.maxBaseHealth;
                        updateHUD();
                        showNotification('❤️ Base soignée par l\'admin !', 'success');
                        break;

                    case 'skip_wave':
                        gameState.enemies.forEach(e => { if (e.health > 0) killEnemy(e.id, false); });
                        gameState.waveActive = false;
                        gameState.wave++;
                        gameState.money += 200;
                        document.getElementById('start-wave-btn').disabled = false;
                        document.getElementById('start-wave-btn').classList.remove('opacity-50');
                        updateHUD();
                        showNotification('⏩ Vague skippée par l\'admin !', 'info');
                        break;

                    case 'god_mode':
                        gameState.godMode = !gameState.godMode;
                        showNotification(
                            gameState.godMode ? '🛡️ God Mode activé par l\'admin !' : '🛡️ God Mode désactivé',
                            'info'
                        );
                        break;

                    case 'nuke':
                        executeNuke();
                        showNotification('☢️ NUKE lancée par l\'admin !', 'warning');
                        break;
                }
            });

        } catch (err) {
            // Silencieux — perte réseau temporaire
        }
    }
    </script>
        <?php include 'includes/footer.php'; ?>
</body>
</html>
