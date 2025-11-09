<?php
ob_start();
session_start();

// --- Config ---
$USERS_FILE = __DIR__.'/users.json'; // lưu acc ngay cùng index.php

if(!file_exists($USERS_FILE)) file_put_contents($USERS_FILE,'{}');
$users = json_decode(file_get_contents($USERS_FILE), true);

// --- Auto login ---
if(!isset($_SESSION['user']) && isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_token'])){
    $u = $_COOKIE['remember_user'];
    $token = $_COOKIE['remember_token'];
    if(isset($users[$u]['token']) && hash_equals($users[$u]['token'],$token)){
        $_SESSION['user'] = $u;
    }
}

// --- Register ---
if(isset($_POST['action']) && $_POST['action']==='register'){
    $u = preg_replace('/[^a-zA-Z0-9_\-]/','', $_POST['username']);
    $p = $_POST['password'];
    if(!$u || !$p){ $error="Vui lòng nhập đầy đủ thông tin"; }
    elseif(isset($users[$u])){ $error="Người dùng đã tồn tại"; }
    else{
        $hash = password_hash($p,PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $users[$u] = ['pass'=>$hash,'token'=>$token];
        if(file_put_contents($USERS_FILE,json_encode($users,JSON_PRETTY_PRINT))===false){
            $error="Lỗi lưu thông tin người dùng!";
        } else {
            $success="Đăng ký thành công! Bây giờ đăng nhập nhé.";
        }
    }
}

// --- Login ---
if(isset($_POST['action']) && $_POST['action']==='login'){
    $u = preg_replace('/[^a-zA-Z0-9_\-]/','', $_POST['username']);
    $p = $_POST['password'];
    if(isset($users[$u]) && password_verify($p,$users[$u]['pass'])){
        $_SESSION['user'] = $u;
        setcookie('remember_user',$u,time()+60*60*24*365*10,'/');
        setcookie('remember_token',$users[$u]['token'],time()+60*60*24*365*10,'/');
        header("Location: ".$_SERVER['PHP_SELF']); exit;
    } else { $error="Đăng nhập thất bại"; }
}

// --- Logout ---
if(isset($_GET['logout'])){
    session_destroy();
    setcookie('remember_user','',time()-3600,'/');
    setcookie('remember_token','',time()-3600,'/');
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

// --- User logged in ---
$user = $_SESSION['user'] ?? null;

// --- Handle Save file ---
if($user && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['file']) && isset($_POST['code'])){
    $f = preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['file']);
    $path = __DIR__.'/'.$user.'_'.$f;
    file_put_contents($path, $_POST['code']);
    header("Location: ".$_SERVER['PHP_SELF']."?edit=$f"); exit;
}

// --- Serve raw ---
if(isset($_GET['raw']) && $user){
    $f = preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_GET['raw']);
    $path = __DIR__.'/'.$user.'_'.$f;
    if(!file_exists($path)){ http_response_code(404); echo "Không tìm thấy file"; exit; }
    header('Content-Type: text/plain; charset=utf-8');
    readfile($path); exit;
}

// --- Delete file ---
if(isset($_GET['del']) && $user){
    $f = preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_GET['del']);
    $path = __DIR__.'/'.$user.'_'.$f;
    if(file_exists($path)) unlink($path);
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

// --- List files ---
$files = [];
if($user){
    foreach(scandir(__DIR__) as $f){
        if(is_file(__DIR__.'/'.$f) && strpos($f, $user.'_')===0){
            $files[] = substr($f, strlen($user)+1);
        }
    }
}
$edit = $_GET['edit'] ?? '';
$edit = preg_replace('/[^a-zA-Z0-9_\-\.]/','',$edit);
$edit_content = $edit && file_exists(__DIR__.'/'.$user.'_'.$edit) ? file_get_contents(__DIR__.'/'.$user.'_'.$edit) : '';
?>

<?php if(!$user): ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raw Nhanh</title>
<style>
body{font-family:Arial,sans-serif;background:#0b1220;color:#eee;text-align:center;padding-top:60px;}
h1{margin-bottom:40px;font-size:36px;}
button{padding:15px 35px;margin:15px;font-size:18px;border:none;border-radius:8px;background:#1e3a8a;color:white;cursor:pointer;transition:0.2s;}
button:hover{background:#2563eb;}
form{margin-top:20px; display:inline-block; text-align:left; background:#111a2c; padding:25px; border-radius:12px; width:300px;}
input[type=text], input[type=password]{padding:10px; width:100%; margin:8px 0; border-radius:6px; border:1px solid #1e3a8a; background:#0f172a; color:#fff;}
p.error{color:#f87171;}
p.success{color:#4ade80;}
</style>
</head>
<body>
<h1>j</h1>
<?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
<?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>
<button onclick="document.getElementById('login').style.display='block';document.getElementById('register').style.display='none'">Đăng nhập</button>
<button onclick="document.getElementById('register').style.display='block';document.getElementById('login').style.display='none'">Đăng ký</button>
<div id="login" style="display:none;margin-top:20px;">
<form method="post">
<input type="hidden" name="action" value="login">
Tên đăng nhập:<br><input type="text" name="username" placeholder="Nhập tên đăng nhập" required><br>
Mật khẩu:<br><input type="password" name="password" placeholder="Nhập mật khẩu" required><br><br>
<button type="submit">Đăng nhập</button>
</form>
</div>
<div id="register" style="display:none;margin-top:20px;">
<form method="post">
<input type="hidden" name="action" value="register">
Tên đăng nhập:<br><input type="text" name="username" placeholder="Tên đăng nhập mới" required><br>
Mật khẩu:<br><input type="password" name="password" placeholder="Mật khẩu mới" required><br><br>
<button type="submit">Đăng ký</button>
</form>
</div>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tạo Raw - <?=$user?></title>
<style>
body{font-family:Arial,sans-serif;background:#0b1220;color:#eee;margin:0;padding:20px;}
h2{display:flex;justify-content:space-between;align-items:center;}
a.logout{color:#f87171;text-decoration:none;font-weight:bold;}
a.logout:hover{text-decoration:underline;}
textarea{width:100%;height:350px;background:#111a2c;color:#fff;border:1px solid #1e3a8a;border-radius:8px;padding:10px;font-family:monospace;}
input[type=text]{width:100%;padding:10px;border-radius:6px;border:1px solid #1e3a8a;background:#111a2c;color:#fff;}
button{padding:8px 15px;margin-top:10px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;transition:0.2s;}
button:hover{background:#3b82f6;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{border:1px solid #1e3a8a;padding:12px;text-align:left;}
th{background:#1e3a8a;color:#fff;}
tr:hover{background:#1e3a8a33;}
a{color:#4ade80;text-decoration:none;font-weight:bold;}
a:hover{color:#60a5fa;}
.card{background:#111a2c;padding:20px;border-radius:12px;margin-top:20px;}
@media(max-width:600px){table, th, td{font-size:14px;padding:8px;} button{width:100%;}}
</style>
</head>
<body>
<h2>Tạo Raw - <?=$user?> <a class="logout" href="?logout">Đăng xuất</a></h2>

<?php if($edit): ?>
<div class="card">
<h3>Chỉnh sửa file: <?=$edit?></h3>
<form method="post">
<input type="hidden" name="file" value="<?=htmlspecialchars($edit)?>">
<textarea name="code"><?=htmlspecialchars($edit_content)?></textarea><br>
<button type="submit">Lưu thay đổi</button>
</form>
<a href="?">⬅ Quay lại danh sách file</a> | <a href="?raw=<?=$edit?>" target="_blank">Xem RAW</a>
</div>
<?php else: ?>
<div class="card">
<h3>Tạo file mới</h3>
<form method="post">
<input type="text" name="file" placeholder="Tên file ví dụ: test.js" required><br><br>
<textarea name="code" placeholder="Dán code vào đây..."></textarea><br>
<button type="submit">Tạo file</button>
</form>
</div>

<div class="card">
<h3>Danh sách file của bạn</h3>
<?php if(count($files)==0){ echo "<p>Chưa có file nào</p>"; } else { ?>
<table>
<tr><th>Tên file</th><th>RAW</th><th>Chỉnh sửa</th><th>Xóa</th></tr>
<?php foreach($files as $f): ?>
<tr>
<td><?=$f?></td>
<td><?php if(filesize(__DIR__.'/'.$user.'_'.$f)>0){ ?><a href="?raw=<?=urlencode($f)?>" target="_blank">RAW</a><?php } ?></td>
<td><a href="?edit=<?=urlencode($f)?>">CHỈNH SỬA</a></td>
<td><a href="?del=<?=urlencode($f)?>" onclick="return confirm('Bạn có chắc muốn xóa file này?')">XÓA</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php } ?>
</div>
<?php endif; ?>
</body>
</html>
