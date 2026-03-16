<?php
require_once __DIR__ . '/../includes/auth.php';
$session             = requireAuth('ADMIN');
$session['fullname'] = $_COOKIE['dms_name'] ?? 'Admin';
$db                  = getDB();
$pageTitle           = 'Retrieval';
$activeNav           = 'retrieval';

$search  = $_GET['q']    ?? '';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 10;

$where=["d.is_deleted=0 AND d.status='ARCHIVED'"];$params=[];
if($search){$where[]='(d.location LIKE ? OR u.fullname LIKE ? OR dt.name LIKE ?)';$params=["%$search%","%$search%","%$search%"];}
$whereSQL=implode(' AND ',$where);
$offset=($page-1)*$perPage;

$countStmt=$db->prepare("SELECT COUNT(*) FROM documents d
    JOIN users u ON u.id=d.owner_id
    JOIN document_types dt ON dt.id=d.document_type_id WHERE $whereSQL");
$countStmt->execute($params);
$totalItems=(int)$countStmt->fetchColumn();
$totalPages=max(1,ceil($totalItems/$perPage));

$stmt=$db->prepare("
    SELECT d.id,d.location,d.status,d.version,d.expiration_date,d.created_at,d.file_path,d.backup_path,
           u.fullname AS owner_name,dt.name AS type_name
    FROM documents d
    JOIN users u ON u.id=d.owner_id
    JOIN document_types dt ON dt.id=d.document_type_id
    WHERE $whereSQL ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$documents=$stmt->fetchAll();

include __DIR__ . '/../includes/layout.php';
?>
<div style="margin-bottom:16px" class="flex items-center gap-2">
  <a href="/dms-php/admin/backup.php" class="btn btn-outline btn-sm"><?=icon('save')?> Backup Management</a>
</div>
<div class="card">
  <div class="card-header">
    <span class="card-title">Archived Documents — Retrieval</span>
    <form method="GET" class="flex items-center gap-2">
      <div class="search-wrap"><?=icon('search')?><input type="text" name="q" class="form-control" placeholder="Search archived..." value="<?=htmlspecialchars($search)?>" style="width:220px"></div>
      <button type="submit" class="btn btn-outline btn-sm"><?=icon('search')?> Search</button>
      <?php if($search): ?><a href="/dms-php/admin/retrieval.php" class="btn btn-ghost btn-sm"><?=icon('x')?> Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Location</th><th>Type</th><th>Owner</th><th>Version</th><th>Archived On</th><th>Expiry</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($documents)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:32px">No archived documents found.</td></tr>
        <?php else: foreach($documents as $doc): ?>
        <tr>
          <td><div style="font-weight:500"><?=htmlspecialchars($doc['location'])?></div><?php if($doc['backup_path']): ?><span class="text-muted text-sm">Has backup</span><?php endif; ?></td>
          <td><?=htmlspecialchars($doc['type_name'])?></td>
          <td><?=htmlspecialchars($doc['owner_name'])?></td>
          <td>v<?=$doc['version']?></td>
          <td class="text-muted"><?=date('M d, Y',strtotime($doc['created_at']))?></td>
          <td><?=$doc['expiration_date']?date('M d, Y',strtotime($doc['expiration_date'])):'—'?></td>
          <td><button class="btn btn-success btn-sm" onclick="restoreDoc('<?=$doc['id']?>')"><?=icon('refresh')?> Restore</button></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <span class="text-muted text-sm">Page <?=$page?> of <?=$totalPages?></span>
    <?php if($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($search)?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($search)?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$extraJs="const BASE='/dms-php';async function restoreDoc(id){if(!confirm('Restore this document to Active status?'))return;try{await apiFetch(BASE+'/api/documents/archive.php',{method:'POST',body:JSON.stringify({id,action:'unarchive'})});showToast('Document restored','success');setTimeout(()=>location.reload(),800);}catch{}}";
include __DIR__ . '/../includes/layout_footer.php';
?>
