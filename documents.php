<?php
require_once __DIR__ . '/includes/auth.php';
$session             = requireAuth();
$session['fullname'] = $_COOKIE['dms_name'] ?? 'User';
$db                  = getDB();
$pageTitle           = 'My Documents';
$activeNav           = 'documents';
$userId              = $session['userId'];

$activeTab = $_GET['tab']  ?? 'my-documents';
$search    = $_GET['q']    ?? '';
$page      = max(1,(int)($_GET['page']??1));
$perPage   = 10;

if($activeTab==='borrows'){
    $where=["bt.borrower_id=?"];$params=[$userId];
    if($search){$where[]='(d.location LIKE ? OR dt.name LIKE ?)';$params=array_merge($params,["%$search%","%$search%"]);}
    $whereSQL=implode(' AND ',$where);$offset=($page-1)*$perPage;
    $cs=$db->prepare("SELECT COUNT(*) FROM borrow_transactions bt JOIN documents d ON d.id=bt.document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE $whereSQL");
    $cs->execute($params);$totalItems=(int)$cs->fetchColumn();$totalPages=max(1,ceil($totalItems/$perPage));
    $stmt=$db->prepare("SELECT bt.id,bt.borrow_date,bt.due_date,bt.return_date,bt.status,d.location,dt.name AS type_name,d.id AS doc_id FROM borrow_transactions bt JOIN documents d ON d.id=bt.document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE $whereSQL ORDER BY bt.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);$rows=$stmt->fetchAll();
}else{
    $where=["d.owner_id=? AND d.is_deleted=0"];$params=[$userId];
    if($search){$where[]='(d.location LIKE ? OR dt.name LIKE ?)';$params=array_merge($params,["%$search%","%$search%"]);}
    $whereSQL=implode(' AND ',$where);$offset=($page-1)*$perPage;
    $cs=$db->prepare("SELECT COUNT(*) FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE $whereSQL");
    $cs->execute($params);$totalItems=(int)$cs->fetchColumn();$totalPages=max(1,ceil($totalItems/$perPage));
    $stmt=$db->prepare("SELECT d.id,d.location,d.status,d.version,d.expiration_date,d.created_at,d.file_path,dt.name AS type_name FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE $whereSQL ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);$rows=$stmt->fetchAll();
}

$availableDocs=$db->query("SELECT d.id,d.location,dt.name AS type_name FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.status='ACTIVE' AND d.is_deleted=0 ORDER BY d.location")->fetchAll();

include __DIR__ . '/includes/layout.php';
?>
<div class="tabs" style="margin-bottom:0">
  <a href="/dms-php/documents.php?tab=my-documents" class="tab-btn <?=$activeTab==='my-documents'?'active':''?>">My Documents</a>
  <a href="/dms-php/documents.php?tab=borrows"      class="tab-btn <?=$activeTab==='borrows'?'active':''?>">My Borrows</a>
</div>

<div class="card" style="border-top-left-radius:0;border-top-right-radius:0;border-top:none">
  <div class="card-header">
    <span class="card-title"><?=$activeTab==='borrows'?'Borrow History':'My Documents'?></span>
    <div class="flex items-center gap-2">
      <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="tab" value="<?=htmlspecialchars($activeTab)?>">
        <div class="search-wrap"><?=icon('search')?><input type="text" name="q" class="form-control" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="width:180px"></div>
        <button type="submit" class="btn btn-outline btn-sm"><?=icon('search')?> Search</button>
        <?php if($search): ?><a href="/dms-php/documents.php?tab=<?=$activeTab?>" class="btn btn-ghost btn-sm"><?=icon('x')?> Clear</a><?php endif; ?>
      </form>
      <?php if($activeTab==='borrows'): ?>
      <button class="btn btn-primary btn-sm" onclick="openModal('requestBorrowModal')"><?=icon('plus')?> Request Borrow</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <?php if($activeTab==='borrows'): ?>
    <table>
      <thead><tr><th>Document</th><th>Type</th><th>Borrow Date</th><th>Due Date</th><th>Return Date</th><th>Status</th></tr></thead>
      <tbody>
        <?php if(empty($rows)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:32px">No borrow history found.</td></tr>
        <?php else: foreach($rows as $r): $st=strtolower($r['status']); ?>
        <tr>
          <td style="font-weight:500"><?=htmlspecialchars($r['location'])?></td>
          <td><?=htmlspecialchars($r['type_name'])?></td>
          <td><?=date('M d, Y',strtotime($r['borrow_date']))?></td>
          <td><?php if($r['due_date']): $ov=$r['status']==='ACTIVE'&&strtotime($r['due_date'])<time(); ?><span <?=$ov?'style="color:var(--danger);font-weight:600"':''?>><?=date('M d, Y',strtotime($r['due_date']))?></span><?php else: ?>—<?php endif; ?></td>
          <td><?=$r['return_date']?date('M d, Y',strtotime($r['return_date'])):'—'?></td>
          <td><span class="badge badge-<?=$st?>"><?=ucfirst($st)?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php else: ?>
    <table>
      <thead><tr><th>Location</th><th>Type</th><th>Version</th><th>Expiry Date</th><th>Status</th><th>Added</th></tr></thead>
      <tbody>
        <?php if(empty($rows)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:32px">No documents found.</td></tr>
        <?php else: foreach($rows as $doc): $st=strtolower($doc['status']); ?>
        <tr>
          <td><div style="font-weight:500"><?=htmlspecialchars($doc['location'])?></div></td>
          <td><?=htmlspecialchars($doc['type_name'])?></td>
          <td>v<?=$doc['version']?></td>
          <td><?=$doc['expiration_date']?date('M d, Y',strtotime($doc['expiration_date'])):'—'?></td>
          <td><span class="badge badge-<?=$st?>"><?=ucfirst($st)?></span></td>
          <td class="text-muted"><?=date('M d, Y',strtotime($doc['created_at']))?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <span class="text-muted text-sm">Page <?=$page?> of <?=$totalPages?></span>
    <?php if($page>1): ?><a href="?tab=<?=$activeTab?>&page=<?=$page-1?>&q=<?=urlencode($search)?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?tab=<?=$activeTab?>&page=<?=$i?>&q=<?=urlencode($search)?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?tab=<?=$activeTab?>&page=<?=$page+1?>&q=<?=urlencode($search)?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal-overlay" id="requestBorrowModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Request to Borrow</span><button class="modal-close"><?=icon('x')?></button></div>
    <form id="requestBorrowForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Document *</label>
          <select name="document_id" class="form-control" required>
            <option value="">Select a document...</option>
            <?php foreach($availableDocs as $d): ?><option value="<?=$d['id']?>">[<?=htmlspecialchars($d['type_name'])?>] <?=htmlspecialchars($d['location'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Needed Until (Due Date)</label><input type="date" name="due_date" class="form-control" min="<?=date('Y-m-d')?>"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('requestBorrowModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=icon('plus')?> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraJs="const BASE='/dms-php';document.getElementById('requestBorrowForm').addEventListener('submit',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target));try{await apiFetch(BASE+'/api/borrow/request.php',{method:'POST',body:JSON.stringify(data)});showToast('Borrow request submitted — awaiting approval','success');closeModal('requestBorrowModal');setTimeout(()=>location.reload(),900);}catch{}});";
include __DIR__ . '/includes/layout_footer.php';
?>
