<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres - CFWT</title>
    <script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="css/all.min.css">
    <link rel="canonical" href="https://cfwt.up.railway.app/members.php">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    
    <div class="min-h-screen py-12">
        <div class="max-w-5xl mx-auto px-4">
            <h1 class="text-4xl font-bold text-white mb-8 text-center">Membres de la Coalition</h1>
            
            <div class="bg-gradient-to-r from-red-900 to-blue-900 p-8 rounded-lg text-center mb-8">
                <i class="fas fa-users text-white text-8xl mb-4"></i>
                <p class="text-white text-xl">
                    Pour voir la liste complète des membres et leurs grades, connectez-vous sur notre Discord !
                </p>
                <a href="<?php echo DISCORD_INVITE; ?>" target="_blank" rel="noopener noreferrer"
                   class="inline-block mt-6 bg-white text-blue-900 px-8 py-3 rounded-lg font-bold hover:bg-gray-200 transition">
                    Rejoindre le Discord
                </a>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-gray-800 p-6 rounded-lg border-t-4 border-red-500">
                    <h3 class="text-xl font-bold text-white mb-3">État-Major</h3>
                    <div class="space-y-2 text-gray-300">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-alt text-red-500"></i>
                            <span>death_angel (Fondateur)</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-alt text-red-400"></i>
                            <span>Enoe_one</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-alt text-red-400"></i>
                            <span>elotokyo</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 p-6 rounded-lg border-t-4 border-blue-500">
                    <h3 class="text-xl font-bold text-white mb-3">Recruteurs</h3>
                    <div class="space-y-2 text-gray-300">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-user-plus text-blue-500"></i>
                            <span>tankman</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 p-6 rounded-lg border-t-4 border-yellow-500">
                    <h3 class="text-xl font-bold text-white mb-3">Modération</h3>
                    <div class="space-y-2 text-gray-300">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-cog text-yellow-500"></i>
                            <span>adamael_huh</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>


