<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();
$id   = $data['id'] ?? null;
if (!$id) jsonResponse(['error' => 'ID required'], 400);

// Fetch the original document
$stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$original = $stmt->fetch();
if (!$original) jsonResponse(['error' => 'Document not found'], 404);

// Create new version
$newId  = generateUUID();
$newVer = $original['version'] + 1;

$stmt = $db->prepare("INSERT INTO documents
  (id, file_path, location, status, version, expiration_date, owner_id, document_type_id, renewed_from_id)
  VALUES (?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)");
$stmt->execute([
    $newId,
    $original['file_path'],
    $original['location'],
    $newVer,
    $original['expiration_date'],
    $original['owner_id'],
    $original['document_type_id'],
    $id,
]);

// Archive the original
$db->prepare("UPDATE documents SET status = 'ARCHIVED' WHERE id = ?")->execute([$id]);

logAudit($session['userId'], 'DOCUMENT_RENEWED', "Renewed to v{$newVer}", $newId);

jsonResponse(['success' => true, 'new_id' => $newId, 'version' => $newVer]);
