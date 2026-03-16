<?php
require_once __DIR__ . '/../includes/auth.php';
$session             = requireAuth('ADMIN');
$session['fullname'] = $_COOKIE['dms_name'] ?? 'Admin';
$db                  = getDB();
$pageTitle           = 'Audit Trail';
$activeNav           = 'audit';

$search   = $_GET['q']         ?? '';
$action   = $_GET['action']    ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 15;

$where=['1=1'];$params=[];
if($search){$where[]='(u.fullname LIKE ? OR al.details LIKE ?)';$params=array_merge($params,["%$search%","%$search%"]);}
if($action){$where[]='al.action=?';$params[]=$action;}
if($dateFrom){$where[]='al.created_at >= ?';$params[]=$dateFrom.' 00:00:00';}
if($dateTo){$where[]='al.created_at <= ?';$params[]=$dateTo.' 23:59:59';}
$whereSQL=implode(' AND ',$where);
$offset=($page-1)*$perPage;

$countStmt=$db->prepare("SELECT COUNT(*) FROM audit_logs al JOIN users u ON u.id=al.user_id WHERE $whereSQL");
$countStmt->execute($params);
$totalItems=(int)$countStmt->fetchColumn();
$totalPages=max(1,ceil($totalItems/$perPage));

$stmt=$db->prepare("
    SELECT al.id,al.action,al.details,al.ip_address,al.created_at,
           u.fullname,u.role,d.location AS doc_location
    FROM audit_logs al
    JOIN users u ON u.id=al.user_id
    LEFT JOIN documents d ON d.id=al.document_id
    WHERE $whereSQL
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs=$stmt->fetchAll();

$actionColors=['DOCUMENT_CREATED'=>'success','DOCUMENT_MODIFIED'=>'borrowed','DOCUMENT_DELETED'=>'expired',
  'DOCUMENT_BACKUP'=>'archived','DOCUMENT_RESTORED'=>'success','DOCUMENT_RENEWED'=>'borrowed',
  'DOCUMENT_ARCHIVED'=>'archived','DOCUMENT_UNARCHIVED'=>'active','DOCUMENT_REQUESTED'=>'pending',
  'DOCUMENT_REFUSED'=>'expired','DOCUMENT_BORROWED'=>'borrowed','DOCUMENT_RETURNED'=>'returned',
  'DOCUMENT_LOCATION_CHANGE'=>'pending','ACCOUNT_CREATED'=>'success','ACCOUNT_MODIFIED'=>'borrowed',
  'ACCOUNT_DELETED'=>'expired','ACCOUNT_LOGIN'=>'active','ACCOUNT_LOGOUT'=>'archived'];

$allActions=['DOCUMENT_CREATED','DOCUMENT_MODIFIED','DOCUMENT_DELETED','DOCUMENT_BACKUP','DOCUMENT_RESTORED',
  'DOCUMENT_RENEWED','DOCUMENT_ARCHIVED','DOCUMENT_UNARCHIVED','DOCUMENT_REQUESTED','DOCUMENT_REFUSED',
  'DOCUMENT_BORROWED','DOCUMENT_RETURNED','DOCUMENT_LOCATION_CHANGE','ACCOUNT_CREATED','ACCOUNT_MODIFIED',
  'ACCOUNT_DELETED','ACCOUNT_LOGIN','ACCOUNT_LOGOUT'];

include __DIR__ . '/../includes/layout.php';
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Audit Trail</span>
    <form method="GET" class="flex items-center gap-2" style="flex-wrap:wrap">
      <div class="search-wrap"><?=icon('search')?><input type="text" name="q" class="form-control" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="width:160px"></div>
      <select name="action" class="form-control" style="width:190px">
        <option value="">All Actions</option>
        <?php foreach($allActions as $a): ?><option value="<?=$a?>" <?=$action===$a?'selected':''?>><?=str_replace('_',' ',$a)?></option><?php endforeach; ?>
      </select>
      <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($dateFrom)?>" style="width:140px">
      <input type="date" name="date_to"   class="form-control" value="<?=htmlspecialchars($dateTo)?>"   style="width:140px">
      <button type="submit" class="btn btn-outline btn-sm"><?=icon('search')?> Filter</button>
      <?php if($search||$action||$dateFrom||$dateTo): ?><a href="/dms-php/admin/audit-trail.php" class="btn btn-ghost btn-sm"><?=icon('x')?> Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Action</th><th>User</th><th>Document</th><th>Details</th><th>IP Address</th><th>Timestamp</th></tr></thead>
      <tbody>
        <?php if(empty($logs)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:32px">No audit logs found.</td></tr>
        <?php else: foreach($logs as $log): ?>
        <tr>
          <td><span class="badge badge-<?=$actionColors[$log['action']]??'archived'?>" style="font-size:.7rem"><?=str_replace('_',' ',$log['action'])?></span></td>
          <td><div style="font-weight:500"><?=htmlspecialchars($log['fullname'])?></div><div class="text-muted" style="font-size:.75rem"><?=$log['role']?></div></td>
          <td class="text-muted"><?=htmlspecialchars($log['doc_location']??'—')?></td>
          <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($log['details']??'')?>"><?=htmlspecialchars($log['details']??'—')?></td>
          <td class="text-muted" style="font-family:monospace;font-size:.78rem"><?=htmlspecialchars($log['ip_address'])?></td>
          <td class="text-muted" style="white-space:nowrap"><?=date('M d, Y H:i',strtotime($log['created_at']))?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  $qs=http_build_query(array_filter(['q'=>$search,'action'=>$action,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
  if($totalPages>1): ?>
  <div class="pagination">
    <span class="text-muted text-sm">Page <?=$page?> of <?=$totalPages?> (<?=$totalItems?> entries)</span>
    <?php if($page>1): ?><a href="?page=<?=$page-1?>&<?=$qs?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?page=<?=$i?>&<?=$qs?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&<?=$qs?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
