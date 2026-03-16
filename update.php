<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();

$id       = $data['id'] ?? null;
$fullname = trim($data['fullname'] ?? '');
$email    = trim($data['email'] ?? '');
if (!$id || !$fullname || !$email) jsonResponse(['error' => 'id, fullname and email are required'], 400);

$role       = $data['role'] ?? 'USER';
$birthDate  = $data['birth_date']     ?? null;
$contact    = $data['contact_number'] ?? null;
$address    = $data['address']        ?? null;

// Check email uniqueness (excluding current user)
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) jsonResponse(['error' => 'Email already in use'], 400);

$db->prepare("UPDATE users SET fullname=?, email=?, role=?, birth_date=?, contact_number=?, address=? WHERE id=?")
   ->execute([$fullname, $email, $role, $birthDate ?: null, $contact ?: null, $address ?: null, $id]);

// Update password if provided
$password = $data['password'] ?? '';
if ($password) {
    if (strlen($password) < 6) jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
}

logAudit($session['userId'], 'ACCOUNT_MODIFIED', "Modified user: $email");

jsonResponse(['success' => true]);
