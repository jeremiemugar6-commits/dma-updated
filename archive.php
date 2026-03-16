<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data   = json_decode(file_get_contents('php://input'), true);
$db     = getDB();
$id     = $data['id'] ?? null;
$action = $data['action'] ?? 'archive'; // 'archive' or 'unarchive'

if (!$id) jsonResponse(['error' => 'ID required'], 400);

$newStatus  = $action === 'unarchive' ? 'ACTIVE' : 'ARCHIVED';
$auditEvent = $action === 'unarchive' ? 'DOCUMENT_UNARCHIVED' : 'DOCUMENT_ARCHIVED';

$stmt = $db->prepare("UPDATE documents SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $id]);

logAudit($session['userId'], $auditEvent, "Document {$action}d", $id);

jsonResponse(['success' => true]);
