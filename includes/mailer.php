<?php
/* Arquivo: includes/mailer.php */
/* Motor de Envio de E-mails do Bliss OS */

// 1. Carregamento Robusto das Bibliotecas (Evita erro de arquivo não encontrado)
// O sistema tenta achar a pasta libs/PHPMailer subindo um nível (..)
$basePath = __DIR__ . '/../libs/PHPMailer/src/';

if (!file_exists($basePath . 'PHPMailer.php')) {
    // Tenta caminho alternativo caso a pasta 'src' não exista
    $basePath = __DIR__ . '/../libs/PHPMailer/';
}

require_once $basePath . 'Exception.php';
require_once $basePath . 'PHPMailer.php';
require_once $basePath . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se a função já existe para não dar erro de redeclaração
if (!function_exists('enviarEmailAcesso')) {

    function enviarEmailAcesso($destinatarioEmail, $destinatarioNome, $senhaTemporaria) {
        $mail = new PHPMailer(true);

        try {
            // --- CONFIGURAÇÕES DO SERVIDOR (GMAIL) ---
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // SEU EMAIL DE DONO
            $mail->Username   = 'alissonmgouveia.pro@gmail.com'; 
            
            // ATENÇÃO: COLOQUE AQUI A SENHA DE APP DO GOOGLE (16 dígitos)
            // Não coloque sua senha de login normal!
            $mail->Password   = 'pro_2024_profissional'; 

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Criptografia SSL
            $mail->Port       = 465; 
            $mail->CharSet    = 'UTF-8';

            // --- REMETENTE (TEM QUE SER IGUAL AO USERNAME NO GMAIL) ---
            $mail->setFrom('alissonmgouveia.pro@gmail.com', 'Bliss OS System');

            // --- DESTINATÁRIO ---
            $mail->addAddress($destinatarioEmail, $destinatarioNome);

            // --- CONTEÚDO DO E-MAIL ---
            $mail->isHTML(true);
            $mail->Subject = 'Bem-vindo ao Bliss OS - Seus Dados de Acesso';

            // Link do sistema (tenta detectar se é localhost ou site real)
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Ajuste o '/app-agencia' se sua pasta tiver outro nome no servidor
            $loginLink = "$protocol://$host/app-agencia/login.php";

            $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #111; padding: 20px; text-align: center;'>
                    <h2 style='color: #fff; margin: 0; letter-spacing: 2px;'>BLISS OS</h2>
                </div>
                <div style='padding: 30px; background-color: #fff; color: #333;'>
                    <h3 style='margin-top: 0; color: #000;'>Olá, $destinatarioNome!</h3>
                    <p style='color: #666;'>Você foi cadastrado na nossa plataforma de gestão.</p>
                    <p>Use as credenciais abaixo para entrar:</p>
                    
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #000; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Login:</strong> $destinatarioEmail</p>
                        <p style='margin: 5px 0;'><strong>Senha Provisória:</strong> $senhaTemporaria</p>
                    </div>

                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='$loginLink' style='background-color: #000; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Acessar Sistema</a>
                    </div>
                </div>
                <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                    Enviado automaticamente por Bliss OS.
                </div>
            </div>";

            $mail->AltBody = "Bem-vindo! Login: $destinatarioEmail | Senha: $senhaTemporaria | Acesse: $loginLink";

            $mail->send();
            return true;

        } catch (Exception $e) {
            // Registra o erro no arquivo de log do servidor para não expor na tela
            error_log("Erro PHPMailer: {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>