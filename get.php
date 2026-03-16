<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$id = $_GET['id'] ?? null;
if (!$id) jsonResponse(['error' => 'ID required'], 400);

$db   = getDB();
$stmt = $db->prepare("SELECT id, fullname, email, birth_date, address, contact_number, role, created_at
                      FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) jsonResponse(['error' => 'User not found'], 404);

jsonResponse($user);
