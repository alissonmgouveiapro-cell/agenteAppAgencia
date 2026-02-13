<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) { header("Location: clientes.php"); exit; }

$id = $_GET['id'];
$tenant = $_SESSION['tenant_id'];

// Ao deletar cliente, o banco deve deletar projetos via CASCADE (se configurado na foreign key)
// Se não, deletamos manualmente:
$pdo->prepare("DELETE FROM projects WHERE client_id = ? AND tenant_id = ?")->execute([$id, $tenant]);
$pdo->prepare("DELETE FROM users WHERE related_client_id = ? AND tenant_id = ?")->execute([$id, $tenant]); // Remove login dele
$pdo->prepare("DELETE FROM clients WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant]);

header("Location: clientes.php?msg=deleted");
?>