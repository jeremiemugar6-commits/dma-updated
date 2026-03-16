<?php
require_once __DIR__ . '/../includes/auth.php';
$session             = requireAuth('ADMIN');
$session['fullname'] = $_COOKIE['dms_name'] ?? 'Admin';
$db                  = getDB();
$pageTitle           = 'Storage';
$activeNav           = 'storage';

// Stats - MySQL compatible
$stats = $db->query("
    SELECT
        SUM(status='ACTIVE')   AS active,
        SUM(status='BORROWED') AS borrowed,
        SUM(status='EXPIRED')  AS expired,
        SUM(status='ARCHIVED') AS archived,
        COUNT(*)               AS total
    FROM documents WHERE is_deleted = 0
")->fetch();

$docTypes = $db->query("SELECT id, name FROM document_types ORDER BY name")->fetchAll();
$users    = $db->query("SELECT id, fullname FROM users WHERE role='USER' ORDER BY fullname")->fetchAll();

$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type']   ?? '';
$search       = $_GET['q']      ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;

$where  = ['d.is_deleted = 0'];
$params = [];
if ($filterStatus) { $where[] = 'd.status = ?';           $params[] = $filterStatus; }
if ($filterType)   { $where[] = 'd.document_type_id = ?'; $params[] = $filterType; }
if ($search) {
    $where[]  = '(d.location LIKE ? OR u.fullname LIKE ? OR dt.name LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$whereSQL = implode(' AND ', $where);
$offset   = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM documents d
    JOIN users u ON u.id=d.owner_id
    JOIN document_types dt ON dt.id=d.document_type_id
    WHERE $whereSQL");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));

$stmt = $db->prepare("
    SELECT d.id, d.location, d.status, d.version, d.expiration_date, d.created_at, d.file_path,
           u.fullname AS owner_name, dt.name AS type_name, d.document_type_id
    FROM documents d
    JOIN users u ON u.id=d.owner_id
    JOIN document_types dt ON dt.id=d.document_type_id
    WHERE $whereSQL
    ORDER BY d.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$documents = $stmt->fetchAll();

include __DIR__ . '/../includes/layout.php';
?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><?= icon('file') ?></div><div><div class="stat-value"><?= $stats['total']??0 ?></div><div class="stat-label">Total Documents</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><?= icon('check') ?></div><div><div class="stat-value"><?= $stats['active']??0 ?></div><div class="stat-label">Active</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><?= icon('eye') ?></div><div><div class="stat-value"><?= $stats['borrowed']??0 ?></div><div class="stat-label">Borrowed</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><?= icon('clock') ?></div><div><div class="stat-value"><?= $stats['expired']??0 ?></div><div class="stat-label">Expired</div></div></div>
  <div class="stat-card"><div class="stat-icon gray"><?= icon('archive') ?></div><div><div class="stat-value"><?= $stats['archived']??0 ?></div><div class="stat-label">Archived</div></div></div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Documents</span>
    <div class="flex items-center gap-2" style="flex-wrap:wrap">
      <form method="GET" class="flex items-center gap-2" style="flex-wrap:wrap">
        <div class="search-wrap"><?= icon('search') ?><input type="text" name="q" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:180px"></div>
        <select name="status" class="form-control" style="width:130px">
          <option value="">All Status</option>
          <?php foreach(['ACTIVE','BORROWED','EXPIRED','ARCHIVED'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(strtolower($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type" class="form-control" style="width:140px">
          <option value="">All Types</option>
          <?php foreach($docTypes as $dt): ?>
          <option value="<?= $dt['id'] ?>" <?= $filterType===$dt['id']?'selected':'' ?>><?= htmlspecialchars($dt['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm"><?= icon('search') ?> Filter</button>
        <?php if($search||$filterStatus||$filterType): ?><a href="/dms-php/admin/storage.php" class="btn btn-ghost btn-sm"><?= icon('x') ?> Clear</a><?php endif; ?>
      </form>
      <button class="btn btn-primary btn-sm" onclick="openModal('addDocModal')"><?= icon('plus') ?> Add Document</button>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Location</th><th>Type</th><th>Owner</th><th>Version</th><th>Expiry</th><th>Status</th><th>Added</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($documents)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:32px">No documents found.</td></tr>
        <?php else: foreach($documents as $doc): $st=strtolower($doc['status']); ?>
        <tr>
          <td><div style="font-weight:500"><?= htmlspecialchars($doc['location']) ?></div></td>
          <td><?= htmlspecialchars($doc['type_name']) ?></td>
          <td><?= htmlspecialchars($doc['owner_name']) ?></td>
          <td>v<?= $doc['version'] ?></td>
          <td><?= $doc['expiration_date'] ? date('M d, Y',strtotime($doc['expiration_date'])) : '—' ?></td>
          <td><span class="badge badge-<?= $st ?>"><?= ucfirst($st) ?></span></td>
          <td class="text-muted"><?= date('M d, Y',strtotime($doc['created_at'])) ?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm"
                onclick="editDoc(this)"
                data-id="<?= htmlspecialchars($doc['id']) ?>"
                data-type="<?= htmlspecialchars($doc['document_type_id']) ?>"
                data-status="<?= htmlspecialchars($doc['status']) ?>"
                data-location="<?= htmlspecialchars($doc['location'], ENT_QUOTES) ?>"
                data-expiry="<?= $doc['expiration_date'] ? substr($doc['expiration_date'],0,10) : '' ?>"
                title="Edit"><?= icon('edit') ?></button>
              <?php if($doc['status']==='ACTIVE'): ?>
              <button class="btn btn-ghost btn-sm" onclick="archiveDoc('<?= $doc['id'] ?>')" title="Archive"><?= icon('archive') ?></button>
              <?php elseif($doc['status']==='ARCHIVED'): ?>
              <button class="btn btn-ghost btn-sm" onclick="unarchiveDoc('<?= $doc['id'] ?>')" title="Restore"><?= icon('refresh') ?></button>
              <?php endif; ?>
              <button class="btn btn-ghost btn-sm" onclick="renewDoc('<?= $doc['id'] ?>')" title="Renew"><?= icon('refresh') ?></button>
              <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="deleteDoc('<?= $doc['id'] ?>')" title="Delete"><?= icon('trash') ?></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <span class="text-muted text-sm">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalItems ?> records)</span>
    <?php if($page>1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&type=<?= urlencode($filterType) ?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&type=<?= urlencode($filterType) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&type=<?= urlencode($filterType) ?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addDocModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Add Document</span><button class="modal-close"><?= icon('x') ?></button></div>
    <form id="addDocForm">
      <div class="modal-body">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Document Type *</label>
            <select name="document_type_id" class="form-control" required>
              <option value="">Select type...</option>
              <?php foreach($docTypes as $dt): ?><option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Owner *</label>
            <select name="owner_id" class="form-control" required>
              <option value="">Select owner...</option>
              <?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Location / Description *</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Cabinet A, Shelf 3" required>
        </div>
        <div class="form-group">
          <label class="form-label">Expiration Date</label>
          <input type="date" name="expiration_date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addDocModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus') ?> Add Document</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editDocModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Document</span><button class="modal-close"><?= icon('x') ?></button></div>
    <form id="editDocForm">
      <input type="hidden" name="id" id="editDocId">
      <div class="modal-body">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Document Type *</label>
            <select name="document_type_id" id="editDocType" class="form-control" required>
              <?php foreach($docTypes as $dt): ?><option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="editDocStatus" class="form-control">
              <option value="ACTIVE">Active</option>
              <option value="BORROWED">Borrowed</option>
              <option value="EXPIRED">Expired</option>
              <option value="ARCHIVED">Archived</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Location *</label>
          <input type="text" name="location" id="editDocLocation" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Expiration Date</label>
          <input type="date" name="expiration_date" id="editDocExpiry" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editDocModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php
$BASE_JS = '/dms-php';
$extraJs = <<<JS
const BASE = '$BASE_JS';
document.getElementById('addDocForm').addEventListener('submit', async e => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  try {
    await apiFetch(BASE+'/api/documents/create.php', {method:'POST',body:JSON.stringify(data)});
    showToast('Document added successfully','success');
    closeModal('addDocModal');
    setTimeout(()=>location.reload(),800);
  } catch{}
});
function editDoc(btn) {
  document.getElementById('editDocId').value       = btn.dataset.id;
  document.getElementById('editDocType').value     = btn.dataset.type;
  document.getElementById('editDocStatus').value   = btn.dataset.status;
  document.getElementById('editDocLocation').value = btn.dataset.location;
  document.getElementById('editDocExpiry').value   = btn.dataset.expiry || '';
  openModal('editDocModal');
}
document.getElementById('editDocForm').addEventListener('submit', async e => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  try {
    await apiFetch(BASE+'/api/documents/update.php',{method:'POST',body:JSON.stringify(data)});
    showToast('Document updated','success');
    closeModal('editDocModal');
    setTimeout(()=>location.reload(),800);
  } catch{}
});
async function archiveDoc(id){
  if(!confirm('Archive this document?'))return;
  try{await apiFetch(BASE+'/api/documents/archive.php',{method:'POST',body:JSON.stringify({id,action:'archive'})});showToast('Archived','success');setTimeout(()=>location.reload(),800);}catch{}
}
async function unarchiveDoc(id){
  if(!confirm('Restore this document?'))return;
  try{await apiFetch(BASE+'/api/documents/archive.php',{method:'POST',body:JSON.stringify({id,action:'unarchive'})});showToast('Restored','success');setTimeout(()=>location.reload(),800);}catch{}
}
async function renewDoc(id){
  if(!confirm('Renew this document? A new version will be created.'))return;
  try{await apiFetch(BASE+'/api/documents/renew.php',{method:'POST',body:JSON.stringify({id})});showToast('Document renewed — new version created','success');setTimeout(()=>location.reload(),900);}catch{}
}
async function deleteDoc(id){
  if(!confirm('Permanently delete this document?'))return;
  try{await apiFetch(BASE+'/api/documents/delete.php',{method:'POST',body:JSON.stringify({id})});showToast('Document deleted','success');setTimeout(()=>location.reload(),800);}catch{}
}
JS;
include __DIR__ . '/../includes/layout_footer.php';
?>
