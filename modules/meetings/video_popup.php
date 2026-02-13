<?php
/* Arquivo: modules/meetings/video_popup.php */
/* Versão: Janela Flutuante + Notificação de Presença (Heartbeat) */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { die("Acesso negado"); }

$active_id = $_GET['id'] ?? null;
if (!$active_id) die("ID da reunião não fornecido.");

// Busca dados da reunião
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ? AND tenant_id = ?");
$stmt->execute([$active_id, $_SESSION['tenant_id']]);
$meeting = $stmt->fetch();

if (!$meeting) die("Reunião não encontrada.");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($meeting['topic']); ?> - Bliss Call</title>
    <script src='https://meet.guifi.net/external_api.js'></script>
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; }
        #meet-frame { width: 100vw; height: 100vh; }
    </style>
</head>
<body>
    <div id="meet-frame"></div>
    <script>
        const domain = 'meet.guifi.net';
        const options = {
            roomName: '<?php echo $meeting['room_name']; ?>',
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#meet-frame'),
            lang: 'pt',
            userInfo: { displayName: '<?php echo $_SESSION['user_name'] ?? "Usuário"; ?>' },
            configOverwrite: { 
                startWithAudioMuted: false, 
                startWithVideoMuted: false,
                disableDeepLinking: true 
            },
            interfaceConfigOverwrite: { 
                SHOW_JITSI_WATERMARK: false,
                TOOLBAR_BUTTONS: ['microphone', 'camera', 'desktop', 'chat', 'raisehand', 'tileview', 'hangup', 'settings']
            }
        };
        const api = new JitsiMeetExternalAPI(domain, options);

        // --- 1. AO SAIR DA CHAMADA ---
        api.addEventListener('videoConferenceLeft', function () {
            // Avisa o backend que saiu e fecha a janela
            navigator.sendBeacon('reuniao.php?ajax_end=1&id=<?php echo $active_id; ?>');
            setTimeout(() => window.close(), 500);
        });

        // --- 2. HEARTBEAT (NOVO) ---
        // Envia um sinal a cada 5 segundos dizendo "Estou aqui"
        // Isso ativa a notificação no painel principal
        setInterval(() => {
            fetch('reuniao.php?api_action=ping&meeting_id=<?php echo $active_id; ?>')
                .catch(err => console.error("Erro no ping", err));
        }, 5000);

    </script>
</body>
</html>