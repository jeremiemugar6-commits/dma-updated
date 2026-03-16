<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();
$id   = $data['id'] ?? null;

if (!$id) jsonResponse(['error' => 'ID required'], 400);
if ($id === $session['userId']) jsonResponse(['error' => 'You cannot delete your own account'], 400);

$stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) jsonResponse(['error' => 'User not found'], 404);

$db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

logAudit($session['userId'], 'ACCOUNT_DELETED', "Deleted user: {$user['email']}");

jsonResponse(['success' => true]);
