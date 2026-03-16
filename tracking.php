<?php
require_once __DIR__ . '/../includes/auth.php';
$session             = requireAuth('ADMIN');
$session['fullname'] = $_COOKIE['dms_name'] ?? 'Admin';
$db                  = getDB();
$pageTitle           = 'Tracking';
$activeNav           = 'tracking';

$stats = $db->query("
    SELECT
        SUM(status='ACTIVE')   AS active,
        SUM(status='PENDING')  AS pending,
        SUM(status='RETURNED') AS returned
    FROM borrow_transactions
")->fetch();

$filterStatus = $_GET['status'] ?? '';
$search       = $_GET['q']      ?? '';
$page         = max(1,(int)($_GET['page']??1));
$perPage      = 10;

$where  = ['1=1'];
$params = [];
if($filterStatus){$where[]='bt.status=?';$params[]=$filterStatus;}
if($search){$where[]='(u.fullname LIKE ? OR d.location LIKE ?)';$params=array_merge($params,["%$search%","%$search%"]);}
$whereSQL = implode(' AND ',$where);
$offset   = ($page-1)*$perPage;

$countStmt=$db->prepare("SELECT COUNT(*) FROM borrow_transactions bt
    JOIN documents d ON d.id=bt.document_id
    JOIN users u ON u.id=bt.borrower_id WHERE $whereSQL");
$countStmt->execute($params);
$totalItems=(int)$countStmt->fetchColumn();
$totalPages=max(1,ceil($totalItems/$perPage));

$stmt=$db->prepare("
    SELECT bt.id,bt.borrow_date,bt.due_date,bt.return_date,bt.status,
           d.id AS doc_id,d.location,dt.name AS type_name,
           u.fullname AS borrower_name
    FROM borrow_transactions bt
    JOIN documents d ON d.id=bt.document_id
    JOIN document_types dt ON dt.id=d.document_type_id
    JOIN users u ON u.id=bt.borrower_id
    WHERE $whereSQL
    ORDER BY bt.created_at DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$transactions=$stmt->fetchAll();

$availableDocs=$db->query("
    SELECT d.id,d.location,dt.name AS type_name
    FROM documents d JOIN document_types dt ON dt.id=d.document_type_id
    WHERE d.status='ACTIVE' AND d.is_deleted=0 ORDER BY d.location")->fetchAll();
$users=$db->query("SELECT id,fullname FROM users WHERE role='USER' ORDER BY fullname")->fetchAll();

include __DIR__ . '/../includes/layout.php';
?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><?=icon('eye')?></div><div><div class="stat-value"><?=$stats['active']??0?></div><div class="stat-label">Currently Borrowed</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><?=icon('clock')?></div><div><div class="stat-value"><?=$stats['pending']??0?></div><div class="stat-label">Pending Requests</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><?=icon('check')?></div><div><div class="stat-value"><?=$stats['returned']??0?></div><div class="stat-label">Returned</div></div></div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Borrow Transactions</span>
    <div class="flex items-center gap-2" style="flex-wrap:wrap">
      <form method="GET" class="flex items-center gap-2" style="flex-wrap:wrap">
        <div class="search-wrap"><?=icon('search')?><input type="text" name="q" class="form-control" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="width:180px"></div>
        <select name="status" class="form-control" style="width:130px">
          <option value="">All Status</option>
          <?php foreach(['ACTIVE','PENDING','RETURNED'] as $s): ?><option value="<?=$s?>" <?=$filterStatus===$s?'selected':''?>><?=ucfirst(strtolower($s))?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm"><?=icon('search')?> Filter</button>
        <?php if($search||$filterStatus): ?><a href="/dms-php/admin/tracking.php" class="btn btn-ghost btn-sm"><?=icon('x')?> Clear</a><?php endif; ?>
      </form>
      <button class="btn btn-primary btn-sm" onclick="openModal('addBorrowModal')"><?=icon('plus')?> New Borrow</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Document</th><th>Borrower</th><th>Borrow Date</th><th>Due Date</th><th>Return Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($transactions)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:32px">No transactions found.</td></tr>
        <?php else: foreach($transactions as $tx): $st=strtolower($tx['status']); ?>
        <tr>
          <td><div style="font-weight:500"><?=htmlspecialchars($tx['location'])?></div><div class="text-muted"><?=htmlspecialchars($tx['type_name'])?></div></td>
          <td><?=htmlspecialchars($tx['borrower_name'])?></td>
          <td><?=date('M d, Y',strtotime($tx['borrow_date']))?></td>
          <td><?php if($tx['due_date']): $overdue=$tx['status']==='ACTIVE'&&strtotime($tx['due_date'])<time(); ?>
            <span <?=$overdue?'style="color:var(--danger);font-weight:600"':''?>><?=date('M d, Y',strtotime($tx['due_date']))?><?=$overdue?' <span class="badge badge-expired">Overdue</span>':''?></span>
          <?php else: ?>—<?php endif; ?></td>
          <td><?=$tx['return_date']?date('M d, Y',strtotime($tx['return_date'])):'—'?></td>
          <td><span class="badge badge-<?=$st?>"><?=ucfirst($st)?></span></td>
          <td>
            <div class="flex gap-2">
              <?php if($tx['status']==='PENDING'): ?>
              <button class="btn btn-success btn-sm" onclick="approveBorrow('<?=$tx['id']?>')"><?=icon('check')?> Approve</button>
              <button class="btn btn-danger btn-sm"  onclick="refuseBorrow('<?=$tx['id']?>')"><?=icon('x')?> Refuse</button>
              <?php elseif($tx['status']==='ACTIVE'): ?>
              <button class="btn btn-primary btn-sm" onclick="returnDoc('<?=$tx['id']?>')"><?=icon('download')?> Return</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <span class="text-muted text-sm">Page <?=$page?> of <?=$totalPages?></span>
    <?php if($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($search)?>&status=<?=urlencode($filterStatus)?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&status=<?=urlencode($filterStatus)?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($search)?>&status=<?=urlencode($filterStatus)?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal-overlay" id="addBorrowModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">New Borrow Transaction</span><button class="modal-close"><?=icon('x')?></button></div>
    <form id="addBorrowForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Document *</label>
          <select name="document_id" class="form-control" required>
            <option value="">Select document...</option>
            <?php foreach($availableDocs as $d): ?><option value="<?=$d['id']?>">[<?=htmlspecialchars($d['type_name'])?>] <?=htmlspecialchars($d['location'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Borrower *</label>
          <select name="borrower_id" class="form-control" required>
            <option value="">Select user...</option>
            <?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['fullname'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Due Date</label>
          <input type="date" name="due_date" class="form-control" min="<?=date('Y-m-d')?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addBorrowModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=icon('plus')?> Create Borrow</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraJs = <<<JS
const BASE='/dms-php';
document.getElementById('addBorrowForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const data=Object.fromEntries(new FormData(e.target));
  try{await apiFetch(BASE+'/api/borrow/create.php',{method:'POST',body:JSON.stringify(data)});showToast('Borrow transaction created','success');closeModal('addBorrowModal');setTimeout(()=>location.reload(),800);}catch{}
});
async function approveBorrow(id){try{await apiFetch(BASE+'/api/borrow/approve.php',{method:'POST',body:JSON.stringify({id})});showToast('Borrow approved','success');setTimeout(()=>location.reload(),800);}catch{}}
async function refuseBorrow(id){if(!confirm('Refuse this borrow request?'))return;try{await apiFetch(BASE+'/api/borrow/refuse.php',{method:'POST',body:JSON.stringify({id})});showToast('Borrow request refused','warning');setTimeout(()=>location.reload(),800);}catch{}}
async function returnDoc(id){if(!confirm('Mark as returned?'))return;try{await apiFetch(BASE+'/api/borrow/return.php',{method:'POST',body:JSON.stringify({id})});showToast('Document returned','success');setTimeout(()=>location.reload(),800);}catch{}}
JS;
include __DIR__ . '/../includes/layout_footer.php';
?>
