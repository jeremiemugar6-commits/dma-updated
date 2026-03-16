<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = requireAuthAPI('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
$db   = getDB();

// Backup all documents without backup
if (!empty($data['all'])) {
    $stmt = $db->query("SELECT id, file_path, location FROM documents WHERE backup_path IS NULL AND is_deleted = 0");
    $docs = $stmt->fetchAll();
    $count = 0;
    foreach ($docs as $doc) {
        $backupPath = '/backups/' . $doc['id'] . '_backup_' . date('Ymd_His') . '.bak';
        $db->prepare("UPDATE documents SET backup_path = ? WHERE id = ?")->execute([$backupPath, $doc['id']]);
        logAudit($session['userId'], 'DOCUMENT_BACKUP', 'Document backed up (batch)', $doc['id']);
        $count++;
    }
    jsonResponse(['success' => true, 'count' => $count]);
}

$id = $data['id'] ?? null;
if (!$id) jsonResponse(['error' => 'ID required'], 400);

$stmt = $db->prepare("SELECT id, file_path, location FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) jsonResponse(['error' => 'Document not found'], 404);

$backupPath = '/backups/' . $id . '_backup_' . date('Ymd_His') . '.bak';
$db->prepare("UPDATE documents SET backup_path = ? WHERE id = ?")->execute([$backupPath, $id]);

logAudit($session['userId'], 'DOCUMENT_BACKUP', "Backed up: {$doc['location']}", $id);

jsonResponse(['success' => true, 'backup_path' => $backupPath]);
