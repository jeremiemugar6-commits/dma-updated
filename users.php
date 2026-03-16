<?php
require_once __DIR__ . '/../includes/auth.php';
$session             = requireAuth('ADMIN');
$session['fullname'] = $_COOKIE['dms_name'] ?? 'Admin';
$db                  = getDB();
$pageTitle           = 'Users Management';
$activeNav           = 'users';

$search  = $_GET['q']    ?? '';
$role    = $_GET['role'] ?? '';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 10;

$where=['1=1'];$params=[];
if($search){$where[]='(u.fullname LIKE ? OR u.email LIKE ?)';$params=array_merge($params,["%$search%","%$search%"]);}
if($role){$where[]='u.role=?';$params[]=$role;}
$whereSQL=implode(' AND ',$where);
$offset=($page-1)*$perPage;

$countStmt=$db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
$countStmt->execute($params);
$totalItems=(int)$countStmt->fetchColumn();
$totalPages=max(1,ceil($totalItems/$perPage));

$stmt=$db->prepare("
    SELECT u.id,u.fullname,u.email,u.role,u.contact_number,u.created_at,
        (SELECT COUNT(*) FROM borrow_transactions bt WHERE bt.borrower_id=u.id AND bt.status='ACTIVE') AS active_borrows
    FROM users u WHERE $whereSQL
    ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users=$stmt->fetchAll();

include __DIR__ . '/../includes/layout.php';
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">All Users</span>
    <div class="flex items-center gap-2" style="flex-wrap:wrap">
      <form method="GET" class="flex items-center gap-2">
        <div class="search-wrap"><?=icon('search')?><input type="text" name="q" class="form-control" placeholder="Search name or email..." value="<?=htmlspecialchars($search)?>" style="width:220px"></div>
        <select name="role" class="form-control" style="width:120px">
          <option value="">All Roles</option>
          <option value="ADMIN" <?=$role==='ADMIN'?'selected':''?>>Admin</option>
          <option value="USER"  <?=$role==='USER'?'selected':''?>>User</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm"><?=icon('search')?> Filter</button>
        <?php if($search||$role): ?><a href="/dms-php/admin/users.php" class="btn btn-ghost btn-sm"><?=icon('x')?> Clear</a><?php endif; ?>
      </form>
      <button class="btn btn-primary btn-sm" onclick="openModal('addUserModal')"><?=icon('plus')?> Add User</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Contact</th><th>Active Borrows</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($users)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:32px">No users found.</td></tr>
        <?php else: foreach($users as $u): ?>
        <tr>
          <td><div class="flex items-center gap-2"><div class="avatar" style="width:30px;height:30px;font-size:.7rem"><?=strtoupper(substr($u['fullname'],0,1))?></div><span style="font-weight:500"><?=htmlspecialchars($u['fullname'])?></span></div></td>
          <td class="text-muted"><?=htmlspecialchars($u['email'])?></td>
          <td><span class="badge badge-<?=strtolower($u['role'])?>"><?=ucfirst(strtolower($u['role']))?></span></td>
          <td class="text-muted"><?=htmlspecialchars($u['contact_number']??'—')?></td>
          <td><?php if($u['active_borrows']>0): ?><span class="badge badge-borrowed"><?=$u['active_borrows']?> active</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
          <td class="text-muted"><?=date('M d, Y',strtotime($u['created_at']))?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm"
                onclick="editUser(this)"
                data-id="<?=htmlspecialchars($u['id'])?>"
                data-fullname="<?=htmlspecialchars($u['fullname'],ENT_QUOTES)?>"
                data-email="<?=htmlspecialchars($u['email'],ENT_QUOTES)?>"
                data-role="<?=htmlspecialchars($u['role'])?>"
                data-contact="<?=htmlspecialchars($u['contact_number']??'',ENT_QUOTES)?>"
                data-birth="<?=$u['birth_date']?substr($u['birth_date'],0,10):''?>"
                data-address="<?=htmlspecialchars($u['address']??'',ENT_QUOTES)?>"
                title="Edit"><?=icon('edit')?></button>
              <?php if($u['id']!==($session['userId']??'')): ?>
              <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="deleteUser('<?=$u['id']?>','<?=addslashes($u['fullname'])?>')" title="Delete"><?=icon('trash')?></button>
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
    <span class="text-muted text-sm">Page <?=$page?> of <?=$totalPages?> (<?=$totalItems?> users)</span>
    <?php if($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($search)?>&role=<?=urlencode($role)?>" class="page-btn">‹</a><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&role=<?=urlencode($role)?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
    <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($search)?>&role=<?=urlencode($role)?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Add User</span><button class="modal-close"><?=icon('x')?></button></div>
    <form id="addUserForm">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="fullname" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
          <div class="form-group"><label class="form-label">Role</label><select name="role" class="form-control"><option value="USER">User</option><option value="ADMIN">Admin</option></select></div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control"></div>
          <div class="form-group"><label class="form-label">Birth Date</label><input type="date" name="birth_date" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=icon('plus')?> Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit User</span><button class="modal-close"><?=icon('x')?></button></div>
    <form id="editUserForm">
      <input type="hidden" name="id" id="editUserId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="fullname" id="editUserName" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" id="editUserEmail" class="form-control" required></div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">New Password <span class="text-muted">(leave blank to keep)</span></label><input type="password" name="password" class="form-control" minlength="6"></div>
          <div class="form-group"><label class="form-label">Role</label><select name="role" id="editUserRole" class="form-control"><option value="USER">User</option><option value="ADMIN">Admin</option></select></div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="contact_number" id="editUserContact" class="form-control"></div>
          <div class="form-group"><label class="form-label">Birth Date</label><input type="date" name="birth_date" id="editUserBirth" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" id="editUserAddress" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=icon('check')?> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraJs=<<<JS
const BASE='/dms-php';
document.getElementById('addUserForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const data=Object.fromEntries(new FormData(e.target));
  try{await apiFetch(BASE+'/api/users/create.php',{method:'POST',body:JSON.stringify(data)});showToast('User created','success');closeModal('addUserModal');setTimeout(()=>location.reload(),800);}catch{}
});
function editUser(btn){
  document.getElementById('editUserId').value      = btn.dataset.id;
  document.getElementById('editUserName').value    = btn.dataset.fullname;
  document.getElementById('editUserEmail').value   = btn.dataset.email;
  document.getElementById('editUserRole').value    = btn.dataset.role;
  document.getElementById('editUserContact').value = btn.dataset.contact||'';
  document.getElementById('editUserBirth').value   = btn.dataset.birth||'';
  document.getElementById('editUserAddress').value = btn.dataset.address||'';
  openModal('editUserModal');
}
document.getElementById('editUserForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const data=Object.fromEntries(new FormData(e.target));
  try{await apiFetch(BASE+'/api/users/update.php',{method:'POST',body:JSON.stringify(data)});showToast('User updated','success');closeModal('editUserModal');setTimeout(()=>location.reload(),800);}catch{}
});
async function deleteUser(id,name){
  if(!confirm('Delete user "'+name+'"?'))return;
  try{await apiFetch(BASE+'/api/users/delete.php',{method:'POST',body:JSON.stringify({id})});showToast('User deleted','success');setTimeout(()=>location.reload(),800);}catch{}
}
JS;
include __DIR__ . '/../includes/layout_footer.php';
?>
