<?php
require_once __DIR__ . '/includes/auth.php';
$session             = requireAuth();
$session['fullname'] = $_COOKIE['dms_name'] ?? 'User';
$db                  = getDB();
$pageTitle           = 'My Profile';
$activeNav           = 'profile';
$userId              = $session['userId'];

$user = $db->prepare("SELECT id,fullname,email,birth_date,address,contact_number,role,created_at FROM users WHERE id=?");
$user->execute([$userId]);
$user = $user->fetch();
if(!$user){header('Location: /dms-php/login.php');exit;}

$ab=$db->prepare("SELECT COUNT(*) FROM borrow_transactions WHERE borrower_id=? AND status='ACTIVE'");$ab->execute([$userId]);$activeBorrows=(int)$ab->fetchColumn();
$tb=$db->prepare("SELECT COUNT(*) FROM borrow_transactions WHERE borrower_id=?");$tb->execute([$userId]);$totalBorrows=(int)$tb->fetchColumn();
$md=$db->prepare("SELECT COUNT(*) FROM documents WHERE owner_id=? AND is_deleted=0");$md->execute([$userId]);$myDocs=(int)$md->fetchColumn();

$success='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['_action']??'';
    if($action==='update_profile'){
        $fullname=$_POST['fullname']??'';
        if(!trim($fullname)){$error='Full name is required.';}
        else{
            $db->prepare("UPDATE users SET fullname=?,contact_number=?,address=?,birth_date=? WHERE id=?")
               ->execute([trim($fullname),$_POST['contact_number']??null,$_POST['address']??null,$_POST['birth_date']??null,$userId]);
            setcookie('dms_name',trim($fullname),time()+SESSION_EXPIRY,'/','',false,false);
            logAudit($userId,'ACCOUNT_MODIFIED','Profile updated');
            $success='Profile updated successfully.';
            $r=$db->prepare("SELECT id,fullname,email,birth_date,address,contact_number,role,created_at FROM users WHERE id=?");
            $r->execute([$userId]);$user=$r->fetch();
            $session['fullname']=trim($fullname);
        }
    }
    if($action==='change_password'){
        $current=$_POST['current_password']??'';$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
        if(!$current||!$new||!$confirm){$error='All fields are required.';}
        elseif($new!==$confirm){$error='New passwords do not match.';}
        elseif(strlen($new)<6){$error='Password must be at least 6 characters.';}
        else{
            $h=$db->prepare("SELECT password_hash FROM users WHERE id=?");$h->execute([$userId]);$hash=$h->fetchColumn();
            if(!password_verify($current,$hash)){$error='Current password is incorrect.';}
            else{
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$userId]);
                logAudit($userId,'ACCOUNT_MODIFIED','Password changed');
                $success='Password changed successfully.';
            }
        }
    }
}
include __DIR__ . '/includes/layout.php';
?>
<?php if($success): ?><div class="toast toast-success" style="margin-bottom:16px;pointer-events:all;display:flex;gap:8px"><?=icon('check')?> <?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="toast toast-error" style="margin-bottom:16px;pointer-events:all;display:flex;gap:8px"><?=icon('x')?> <?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><?=icon('file')?></div><div><div class="stat-value"><?=$myDocs?></div><div class="stat-label">My Documents</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><?=icon('eye')?></div><div><div class="stat-value"><?=$activeBorrows?></div><div class="stat-label">Currently Borrowed</div></div></div>
  <div class="stat-card"><div class="stat-icon gray"><?=icon('list')?></div><div><div class="stat-value"><?=$totalBorrows?></div><div class="stat-label">Total Borrows</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="profile-grid">
  <div class="card">
    <div class="card-header"><span class="card-title">Personal Information</span></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div class="avatar" style="width:56px;height:56px;font-size:1.3rem"><?=strtoupper(substr($user['fullname'],0,1))?></div>
        <div>
          <div style="font-weight:600;font-size:1rem"><?=htmlspecialchars($user['fullname'])?></div>
          <div class="text-muted"><?=htmlspecialchars($user['email'])?></div>
          <span class="badge badge-<?=strtolower($user['role'])?>" style="margin-top:4px"><?=ucfirst(strtolower($user['role']))?></span>
        </div>
      </div>
      <form method="POST" action="/dms-php/profile.php">
        <input type="hidden" name="_action" value="update_profile">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="fullname" class="form-control" value="<?=htmlspecialchars($user['fullname'])?>" required></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control" value="<?=htmlspecialchars($user['email'])?>" disabled style="background:#f8fafc"><div style="font-size:.75rem;color:var(--muted);margin-top:3px">Contact admin to change email</div></div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?=htmlspecialchars($user['contact_number']??'')?>"></div>
          <div class="form-group"><label class="form-label">Birth Date</label><input type="date" name="birth_date" class="form-control" value="<?=$user['birth_date']?substr($user['birth_date'],0,10):''?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?=htmlspecialchars($user['address']??'')?>"></div>
        <button type="submit" class="btn btn-primary w-full"><?=icon('check')?> Update Profile</button>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Change Password</span></div>
    <div class="card-body">
      <form method="POST" action="/dms-php/profile.php">
        <input type="hidden" name="_action" value="change_password">
        <div class="form-group"><label class="form-label">Current Password *</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="form-group"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
        <div class="form-group"><label class="form-label">Confirm New Password *</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
        <button type="submit" class="btn btn-primary w-full"><?=icon('check')?> Change Password</button>
      </form>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)" class="text-muted text-sm">
        <strong>Account created:</strong> <?=date('F d, Y',strtotime($user['created_at']))?>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:768px){.profile-grid{grid-template-columns:1fr!important}}</style>
<?php include __DIR__ . '/includes/layout_footer.php'; ?>
