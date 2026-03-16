<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();
$id   = $data['id'] ?? null;
if (!$id) jsonResponse(['error' => 'ID required'], 400);

$stmt = $db->prepare("SELECT bt.*, d.location FROM borrow_transactions bt
  JOIN documents d ON d.id = bt.document_id WHERE bt.id = ?");
$stmt->execute([$id]);
$tx = $stmt->fetch();
if (!$tx || $tx['status'] !== 'ACTIVE') jsonResponse(['error' => 'Transaction not found or not active'], 400);

$db->prepare("UPDATE borrow_transactions SET status = 'RETURNED', return_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
$db->prepare("UPDATE documents SET status = 'ACTIVE' WHERE id = ?")->execute([$tx['document_id']]);

logAudit($session['userId'], 'DOCUMENT_RETURNED', "Returned: {$tx['location']}", $tx['document_id']);

jsonResponse(['success' => true]);
