<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();

$fullname  = trim($data['fullname'] ?? '');
$email     = trim($data['email'] ?? '');
$password  = $data['password'] ?? '';
$role      = $data['role'] ?? 'USER';

if (!$fullname || !$email || !$password) jsonResponse(['error' => 'fullname, email and password are required'], 400);
if (strlen($password) < 6) jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
if (!in_array($role, ['ADMIN', 'USER'])) $role = 'USER';

// Check unique email
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) jsonResponse(['error' => 'Email already in use'], 400);

$id           = generateUUID();
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$birthDate    = $data['birth_date']     ?? null;
$contactNum   = $data['contact_number'] ?? null;
$address      = $data['address']        ?? null;

$stmt = $db->prepare("INSERT INTO users (id, fullname, email, password_hash, role, birth_date, contact_number, address)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$id, $fullname, $email, $passwordHash, $role, $birthDate ?: null, $contactNum ?: null, $address ?: null]);

logAudit($session['userId'], 'ACCOUNT_CREATED', "Created user: $email");

jsonResponse(['success' => true, 'id' => $id]);
