<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI();

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();

$docId    = $data['document_id'] ?? null;
$dueDate  = $data['due_date'] ?? null;
$userId   = $session['userId'];

if (!$docId) jsonResponse(['error' => 'document_id is required'], 400);

// Check document availability
$stmt = $db->prepare("SELECT id, location FROM documents WHERE id = ? AND status = 'ACTIVE' AND is_deleted = 0");
$stmt->execute([$docId]);
$doc = $stmt->fetch();
if (!$doc) jsonResponse(['error' => 'Document is not available'], 400);

// Check for existing pending/active request from this user
$stmt = $db->prepare("SELECT id FROM borrow_transactions
  WHERE document_id = ? AND borrower_id = ? AND status IN ('PENDING','ACTIVE')");
$stmt->execute([$docId, $userId]);
if ($stmt->fetch()) jsonResponse(['error' => 'You already have an active or pending request for this document'], 400);

$txId = generateUUID();
$db->prepare("INSERT INTO borrow_transactions (id, document_id, borrower_id, due_date, status)
              VALUES (?, ?, ?, ?, 'PENDING')")->execute([$txId, $docId, $userId, $dueDate ?: null]);

logAudit($userId, 'DOCUMENT_REQUESTED', "Requested: {$doc['location']}", $docId);

jsonResponse(['success' => true, 'id' => $txId]);
