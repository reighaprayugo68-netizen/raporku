<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $hostName = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
        $isLocal = str_starts_with($hostName, 'localhost') || str_starts_with($hostName, '127.0.0.1');
        $captchaOk = false;
        if ($isLocal && $turnstileSiteKey === '' && $turnstileSecretKey === '') {
            // Mode lokal untuk pengembangan XAMPP saja; CAPTCHA wajib diaktifkan saat online.
            $captchaOk = true;
        } elseif ($turnstileSiteKey !== '' && $turnstileSecretKey !== '' && !empty($_POST['cf-turnstile-response'])) {
            $verify = http_build_query([
                'secret' => $turnstileSecretKey,
                'response' => $_POST['cf-turnstile-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $context = stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $verify, 'timeout' => 8
            ]]);
            $verifyResult = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
            $verifyData = $verifyResult ? json_decode($verifyResult, true) : null;
            $captchaOk = is_array($verifyData) && !empty($verifyData['success']);
        }
        if (!$captchaOk) {
            $error = 'Verifikasi Saya bukan robot belum berhasil. Silakan coba lagi.';
        } else {
        $stmt = $pdo->prepare("SELECT u.*, s.nama AS nama_siswa FROM users u LEFT JOIN siswa s ON s.id=u.siswa_id WHERE u.username=? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$user['id'],'username'=>$user['username'],'role'=>$user['role'],'siswa_id'=>$user['siswa_id'],'nama'=>$user['nama_siswa'] ?: $user['username']];
            redirect('index.php');
        }
        $error = 'Username atau kata sandi salah.';
        }
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        redirect('index.php');
    } else {
        require_admin();
        try {
            switch ($action) {
                case 'save_student':
                    $id=(int)($_POST['id']??0); $nis=trim($_POST['nis']??''); $nisn=trim($_POST['nisn']??'');
                    $name=trim($_POST['nama']??''); $class=(int)($_POST['kelas_id']??0);
                    if ($id) {
                        $st=$pdo->prepare("UPDATE siswa SET nis=?,nisn=?,nama=?,jenis_kelamin=?,alamat=?,kelas_id=? WHERE id=?");
                        $st->execute([$nis,$nisn?:null,$name,$_POST['jenis_kelamin']?:null,trim($_POST['alamat']??''),$class,$id]);
                        $pdo->prepare("UPDATE users SET username=? WHERE siswa_id=?")->execute([$nis,$id]);
                    } else {
                        $st=$pdo->prepare("INSERT INTO siswa (nis,nisn,nama,jenis_kelamin,alamat,kelas_id) VALUES (?,?,?,?,?,?)");
                        $st->execute([$nis,$nisn?:null,$name,$_POST['jenis_kelamin']?:null,trim($_POST['alamat']??''),$class]);
                        $sid=(int)$pdo->lastInsertId();
                        $pdo->prepare("INSERT INTO users (username,password,role,siswa_id) VALUES (?,?, 'siswa',?)")->execute([$nis,password_hash('12345',PASSWORD_DEFAULT),$sid]);
                    }
                    $success='Data siswa berhasil disimpan.'; break;
                case 'delete_student':
                    $pdo->prepare("DELETE FROM siswa WHERE id=?")->execute([(int)$_POST['id']]);
                    $success='Data siswa berhasil dihapus.'; break;
                case 'save_class':
                    $id=(int)($_POST['id']??0);
                    if ($id) $pdo->prepare("UPDATE kelas SET nama=?,tingkat=?,keterangan=? WHERE id=?")->execute([trim($_POST['nama']), (int)$_POST['tingkat'],trim($_POST['keterangan']),$id]);
                    else $pdo->prepare("INSERT INTO kelas (nama,tingkat,keterangan) VALUES (?,?,?)")->execute([trim($_POST['nama']),(int)$_POST['tingkat'],trim($_POST['keterangan'])]);
                    $success='Data kelas berhasil disimpan.'; break;
                case 'delete_class':
                    $pdo->prepare("DELETE FROM kelas WHERE id=?")->execute([(int)$_POST['id']]); $success='Kelas berhasil dihapus.'; break;
                case 'save_subject':
                    $id=(int)($_POST['id']??0);
                    if ($id) $pdo->prepare("UPDATE mata_pelajaran SET nama=?,kode=? WHERE id=?")->execute([trim($_POST['nama']),trim($_POST['kode'])?:null,$id]);
                    else $pdo->prepare("INSERT INTO mata_pelajaran (nama,kode) VALUES (?,?)")->execute([trim($_POST['nama']),trim($_POST['kode'])?:null]);
                    $success='Mata pelajaran berhasil disimpan.'; break;
                case 'delete_subject':
                    $pdo->prepare("DELETE FROM mata_pelajaran WHERE id=?")->execute([(int)$_POST['id']]); $success='Mata pelajaran berhasil dihapus.'; break;
                case 'save_year':
                    $id=(int)($_POST['id']??0); $active=isset($_POST['aktif'])?1:0;
                    $pdo->beginTransaction();
                    if ($active) $pdo->exec("UPDATE tahun_ajaran SET aktif=0");
                    if ($id) $pdo->prepare("UPDATE tahun_ajaran SET nama=?,semester=?,aktif=? WHERE id=?")->execute([trim($_POST['nama']),$_POST['semester'],$active,$id]);
                    else $pdo->prepare("INSERT INTO tahun_ajaran (nama,semester,aktif) VALUES (?,?,?)")->execute([trim($_POST['nama']),$_POST['semester'],$active]);
                    $pdo->commit(); $success='Tahun ajaran berhasil disimpan.'; break;
                case 'delete_year':
                    $pdo->prepare("DELETE FROM tahun_ajaran WHERE id=?")->execute([(int)$_POST['id']]); $success='Tahun ajaran berhasil dihapus.'; break;
                case 'save_grade':
                    $id=(int)($_POST['id']??0); $score=(float)$_POST['nilai'];
                    $pred=$score>=90?'A':($score>=85?'B+':($score>=80?'B':'C'));
                    if ($id) $pdo->prepare("UPDATE nilai SET siswa_id=?,mapel_id=?,tahun_ajaran_id=?,nilai=?,predikat=?,catatan=? WHERE id=?")->execute([(int)$_POST['siswa_id'],(int)$_POST['mapel_id'],(int)$_POST['tahun_ajaran_id'],$score,$pred,trim($_POST['catatan']),$id]);
                    else $pdo->prepare("INSERT INTO nilai (siswa_id,mapel_id,tahun_ajaran_id,nilai,predikat,catatan) VALUES (?,?,?,?,?,?)")->execute([(int)$_POST['siswa_id'],(int)$_POST['mapel_id'],(int)$_POST['tahun_ajaran_id'],$score,$pred,trim($_POST['catatan'])]);
                    $success='Nilai berhasil disimpan.'; break;
                case 'delete_grade':
                    $pdo->prepare("DELETE FROM nilai WHERE id=?")->execute([(int)$_POST['id']]); $success='Nilai berhasil dihapus.'; break;
                case 'reset_password':
                    $sid=(int)$_POST['siswa_id'];
                    $pdo->prepare("UPDATE users SET password=? WHERE siswa_id=?")->execute([password_hash('12345',PASSWORD_DEFAULT),$sid]);
                    $success='Password siswa direset menjadi 12345.'; break;
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Operasi gagal: ' . $ex->getMessage();
        }
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
$editId=(int)($_GET['edit']??0);
function rows(PDO $pdo,string $sql): array { return $pdo->query($sql)->fetchAll(); }
$classes=rows($pdo,"SELECT * FROM kelas ORDER BY tingkat");
$subjects=rows($pdo,"SELECT * FROM mata_pelajaran ORDER BY nama");
$years=rows($pdo,"SELECT * FROM tahun_ajaran ORDER BY id DESC");
$students=rows($pdo,"SELECT s.*,k.nama AS kelas FROM siswa s JOIN kelas k ON k.id=s.kelas_id ORDER BY k.tingkat,s.nama");
$grades=rows($pdo,"SELECT n.*,s.nama AS siswa,s.nis,m.nama AS mapel,t.nama AS tahun FROM nilai n JOIN siswa s ON s.id=n.siswa_id JOIN mata_pelajaran m ON m.id=n.mapel_id JOIN tahun_ajaran t ON t.id=n.tahun_ajaran_id ORDER BY s.nama,m.nama");
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RaporKu</title>
<style>
*{box-sizing:border-box}body{margin:0;font:15px Arial,sans-serif;background:#f3f6fb;color:#243247}a{color:#2563eb;text-decoration:none}
.top{background:#fff;padding:17px 5%;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5eaf1}.brand{font-size:25px;font-weight:bold;color:#1769aa}
.wrap{max-width:1200px;margin:28px auto;padding:0 18px}.login{max-width:430px;margin:65px auto;background:white;padding:32px;border-radius:16px;box-shadow:0 8px 28px #172b4d15}
input,select,textarea{width:100%;padding:11px;border:1px solid #ccd5e1;border-radius:8px;margin:6px 0 14px;font:inherit}label{font-weight:600;font-size:13px}
button,.btn{border:0;border-radius:8px;padding:10px 15px;background:#2563eb;color:white;cursor:pointer;font-weight:600;display:inline-block}.danger{background:#dc2626}.muted{background:#64748b}.green{background:#15803d}
.alert{padding:12px;border-radius:8px;margin:12px 0;background:#e0f2fe;color:#075985}.err{background:#fee2e2;color:#991b1b}
.layout{display:grid;grid-template-columns:220px 1fr;gap:22px}.side,.panel{background:white;border-radius:14px;padding:20px;box-shadow:0 3px 15px #172b4d0a}.side a{display:block;padding:11px;border-radius:8px;color:#334155;margin:3px 0}.side a.active,.side a:hover{background:#eaf1ff;color:#2563eb}
h1,h2,h3{color:#17324d;margin-top:0}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:18px 0}.stat{background:#fff;padding:20px;border-radius:12px;box-shadow:0 3px 12px #172b4d0a}.stat b{font-size:27px;color:#2563eb;display:block;margin-top:8px}
table{width:100%;border-collapse:collapse;background:white}th,td{padding:11px;border-bottom:1px solid #e8edf3;text-align:left;font-size:14px}th{background:#f8fafc;color:#475569}.table-wrap{overflow:auto}
.grid2{display:grid;grid-template-columns: minmax(250px,340px) 1fr;gap:18px}.formbox{background:#f8fafc;padding:16px;border-radius:10px;height:max-content}
.small{font-size:12px;color:#64748b}.actions{display:flex;gap:6px;flex-wrap:wrap}.inline{display:inline}.welcome{padding:24px;background:linear-gradient(120deg,#2563eb,#5794f7);color:white;border-radius:14px;margin-bottom:18px}.welcome h2{color:white}
@media(max-width:800px){.layout,.grid2{grid-template-columns:1fr}.top{padding:14px}.wrap{margin:15px auto}.side{display:flex;flex-wrap:wrap;gap:5px}.side a{padding:8px}}
</style></head><body>
<header class="top"><div class="brand">📚 RaporKu</div><?php if(isset($_SESSION['user'])): ?><div><?=e($_SESSION['user']['nama'])?> · <?=e($_SESSION['user']['role'])?> <form class="inline" method="post"><input type="hidden" name="action" value="logout"><button class="muted">Keluar</button></form></div><?php endif ?></header>
<div class="wrap">
<?php if($error): ?><div class="alert err"><?=e($error)?></div><?php endif ?>
<?php if($success): ?><div class="alert"><?=e($success)?></div><?php endif ?>

<?php if(!isset($_SESSION['user'])): ?>
<div class="login"><div style="font-size:13px;color:#1769aa;font-weight:bold">TERHUBUNG DALAM PENDIDIKAN</div><h1>Login RaporKu</h1><p class="small">Masuk sebagai administrator atau siswa untuk mengakses data dan rapor.</p>
<form method="post"><input type="hidden" name="action" value="login"><?php if($turnstileSiteKey !== ''): ?><div class="cf-turnstile" data-sitekey="<?=e($turnstileSiteKey)?>" style="margin:16px 0"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php else: ?><div class="alert" style="margin:14px 0"><b>Verifikasi Saya bukan robot</b><br><span class="small">Mode lokal XAMPP. CAPTCHA keamanan akan aktif setelah Site Key dan Secret Key Cloudflare diatur untuk website online.</span></div><?php endif ?><label>Username / NIS</label><input name="username" required autofocus placeholder="Masukkan username atau NIS"><label>Kata sandi</label><input type="password" name="password" required placeholder="Masukkan kata sandi"><button style="width:100%">Masuk</button></form>
<p class="small" style="margin-top:18px">Demo admin: admin / admin123<br>Akun siswa: gunakan NIS / 12345</p></div>

<?php elseif(is_admin()): ?>
<div class="layout"><nav class="side"><h3>Menu Admin</h3>
<?php foreach(['dashboard'=>'Dashboard','siswa'=>'Data Siswa','kelas'=>'Data Kelas','mapel'=>'Mata Pelajaran','tahun'=>'Tahun Ajaran','nilai'=>'Data Nilai'] as $key=>$label): ?><a class="<?=$tab===$key?'active':''?>" href="?tab=<?=$key?>"><?=e($label)?></a><?php endforeach ?>
<p class="small" style="margin:15px 5px">Kelola seluruh data RaporKu.</p></nav>
<main>
<?php if($tab==='dashboard'): ?>
<h1>Dashboard Administrator</h1><p>Ringkasan data yang tersimpan di database MySQL.</p>
<div class="cards"><div class="stat">Jumlah Siswa<b><?=count($students)?></b></div><div class="stat">Jumlah Kelas<b><?=count($classes)?></b></div><div class="stat">Mata Pelajaran<b><?=count($subjects)?></b></div><div class="stat">Data Nilai<b><?=count($grades)?></b></div></div>
<div class="panel"><h3>Selamat datang di RaporKu</h3><p>Gunakan menu di sebelah kiri untuk menambah, melihat, mengubah, dan menghapus data siswa, kelas, mata pelajaran, tahun ajaran, serta nilai rapor.</p></div>
<?php elseif($tab==='siswa'): $edit=null; if($editId){$q=$pdo->prepare("SELECT * FROM siswa WHERE id=?");$q->execute([$editId]);$edit=$q->fetch();} ?>
<h1>Data Siswa</h1><div class="grid2"><form class="formbox" method="post"><h3><?=$edit?'Edit':'Tambah'?> Siswa</h3><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?=$edit['id']??0?>">
<label>NIS</label><input name="nis" required value="<?=e($edit['nis']??'')?>"><label>NISN</label><input name="nisn" value="<?=e($edit['nisn']??'')?>"><label>Nama lengkap</label><input name="nama" required value="<?=e($edit['nama']??'')?>"><label>Jenis kelamin</label><select name="jenis_kelamin"><option value="">- Pilih -</option><option value="L" <?=($edit['jenis_kelamin']??'')==='L'?'selected':''?>>Laki-laki</option><option value="P" <?=($edit['jenis_kelamin']??'')==='P'?'selected':''?>>Perempuan</option></select><label>Kelas</label><select name="kelas_id" required><?php foreach($classes as $c):?><option value="<?=$c['id']?>" <?=($edit['kelas_id']??'')==$c['id']?'selected':''?>><?=e($c['nama'])?></option><?php endforeach?></select><label>Alamat</label><textarea name="alamat"><?=e($edit['alamat']??'')?></textarea><button>Simpan</button> <?php if($edit):?><a class="btn muted" href="?tab=siswa">Batal</a><?php endif?><p class="small">Akun siswa baru otomatis dibuat dengan username NIS dan password awal 12345.</p></form>
<div class="panel"><h3>Daftar Siswa (<?=count($students)?>)</h3><div class="table-wrap"><table><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Aksi</th></tr><?php foreach($students as $s):?><tr><td><?=e($s['nis'])?></td><td><?=e($s['nama'])?></td><td><?=e($s['kelas'])?></td><td><div class="actions"><a class="btn" href="?tab=siswa&edit=<?=$s['id']?>">Edit</a><form method="post" onsubmit="return confirm('Hapus siswa ini?')"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="danger">Hapus</button></form><form method="post" onsubmit="return confirm('Reset password menjadi 12345?')"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="siswa_id" value="<?=$s['id']?>"><button class="muted">Reset PW</button></form></div></td></tr><?php endforeach?></table></div></div></div>
<?php elseif($tab==='kelas'): $edit=null;if($editId){$q=$pdo->prepare("SELECT * FROM kelas WHERE id=?");$q->execute([$editId]);$edit=$q->fetch();} ?>
<h1>Data Kelas</h1><div class="grid2"><form class="formbox" method="post"><h3><?=$edit?'Edit':'Tambah'?> Kelas</h3><input type="hidden" name="action" value="save_class"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><label>Nama kelas</label><input name="nama" required value="<?=e($edit['nama']??'')?>"><label>Tingkat (7-9)</label><input type="number" min="7" max="9" name="tingkat" required value="<?=e($edit['tingkat']??'7')?>"><label>Keterangan</label><input name="keterangan" value="<?=e($edit['keterangan']??'')?>"><button>Simpan</button></form><div class="panel"><div class="table-wrap"><table><tr><th>Nama</th><th>Tingkat</th><th>Keterangan</th><th>Aksi</th></tr><?php foreach($classes as $c):?><tr><td><?=e($c['nama'])?></td><td><?=e($c['tingkat'])?></td><td><?=e($c['keterangan'])?></td><td><div class="actions"><a class="btn" href="?tab=kelas&edit=<?=$c['id']?>">Edit</a><form method="post" onsubmit="return confirm('Hapus kelas?')"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="danger">Hapus</button></form></div></td></tr><?php endforeach?></table></div></div></div>
<?php elseif($tab==='mapel'): $edit=null;if($editId){$q=$pdo->prepare("SELECT * FROM mata_pelajaran WHERE id=?");$q->execute([$editId]);$edit=$q->fetch();} ?>
<h1>Mata Pelajaran</h1><div class="grid2"><form class="formbox" method="post"><h3><?=$edit?'Edit':'Tambah'?> Mapel</h3><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><label>Nama mata pelajaran</label><input name="nama" required value="<?=e($edit['nama']??'')?>"><label>Kode</label><input name="kode" value="<?=e($edit['kode']??'')?>"><button>Simpan</button></form><div class="panel"><div class="table-wrap"><table><tr><th>Nama</th><th>Kode</th><th>Aksi</th></tr><?php foreach($subjects as $s):?><tr><td><?=e($s['nama'])?></td><td><?=e($s['kode'])?></td><td><div class="actions"><a class="btn" href="?tab=mapel&edit=<?=$s['id']?>">Edit</a><form method="post" onsubmit="return confirm('Hapus mapel?')"><input type="hidden" name="action" value="delete_subject"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="danger">Hapus</button></form></div></td></tr><?php endforeach?></table></div></div></div>
<?php elseif($tab==='tahun'): $edit=null;if($editId){$q=$pdo->prepare("SELECT * FROM tahun_ajaran WHERE id=?");$q->execute([$editId]);$edit=$q->fetch();} ?>
<h1>Tahun Ajaran</h1><div class="grid2"><form class="formbox" method="post"><h3><?=$edit?'Edit':'Tambah'?> Tahun Ajaran</h3><input type="hidden" name="action" value="save_year"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><label>Tahun pelajaran</label><input name="nama" required placeholder="2026/2027" value="<?=e($edit['nama']??'')?>"><label>Semester</label><select name="semester"><option <?=($edit['semester']??'')==='Ganjil'?'selected':''?>>Ganjil</option><option <?=($edit['semester']??'')==='Genap'?'selected':''?>>Genap</option></select><label><input style="width:auto" type="checkbox" name="aktif" value="1" <?=($edit['aktif']??0)?'checked':''?>> Jadikan aktif</label><br><button>Simpan</button></form><div class="panel"><div class="table-wrap"><table><tr><th>Tahun</th><th>Semester</th><th>Status</th><th>Aksi</th></tr><?php foreach($years as $y):?><tr><td><?=e($y['nama'])?></td><td><?=e($y['semester'])?></td><td><?=$y['aktif']?'Aktif':'-'?></td><td><div class="actions"><a class="btn" href="?tab=tahun&edit=<?=$y['id']?>">Edit</a><form method="post" onsubmit="return confirm('Hapus tahun ajaran?')"><input type="hidden" name="action" value="delete_year"><input type="hidden" name="id" value="<?=$y['id']?>"><button class="danger">Hapus</button></form></div></td></tr><?php endforeach?></table></div></div></div>
<?php elseif($tab==='nilai'): $edit=null;if($editId){$q=$pdo->prepare("SELECT * FROM nilai WHERE id=?");$q->execute([$editId]);$edit=$q->fetch();} ?>
<h1>Data Nilai Rapor</h1><div class="grid2"><form class="formbox" method="post"><h3><?=$edit?'Edit':'Tambah'?> Nilai</h3><input type="hidden" name="action" value="save_grade"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><label>Siswa</label><select name="siswa_id" required><?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=($edit['siswa_id']??'')==$s['id']?'selected':''?>><?=e($s['nama'].' ('.$s['nis'].')')?></option><?php endforeach?></select><label>Mata pelajaran</label><select name="mapel_id" required><?php foreach($subjects as $s):?><option value="<?=$s['id']?>" <?=($edit['mapel_id']??'')==$s['id']?'selected':''?>><?=e($s['nama'])?></option><?php endforeach?></select><label>Tahun ajaran</label><select name="tahun_ajaran_id" required><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=($edit['tahun_ajaran_id']??'')==$y['id']?'selected':''?>><?=e($y['nama'].' - '.$y['semester'])?></option><?php endforeach?></select><label>Nilai (0-100)</label><input type="number" name="nilai" min="0" max="100" step=".01" required value="<?=e($edit['nilai']??'80')?>"><label>Catatan</label><textarea name="catatan"><?=e($edit['catatan']??'')?></textarea><button>Simpan</button></form><div class="panel"><h3>Daftar Nilai (<?=count($grades)?>)</h3><div class="table-wrap"><table><tr><th>Siswa</th><th>Mapel</th><th>Tahun</th><th>Nilai</th><th>Predikat</th><th>Aksi</th></tr><?php foreach($grades as $g):?><tr><td><?=e($g['siswa'])?><br><span class="small"><?=e($g['nis'])?></span></td><td><?=e($g['mapel'])?></td><td><?=e($g['tahun'])?></td><td><?=e($g['nilai'])?></td><td><?=e($g['predikat'])?></td><td><div class="actions"><a class="btn" href="?tab=nilai&edit=<?=$g['id']?>">Edit</a><form method="post" onsubmit="return confirm('Hapus nilai?')"><input type="hidden" name="action" value="delete_grade"><input type="hidden" name="id" value="<?=$g['id']?>"><button class="danger">Hapus</button></form></div></td></tr><?php endforeach?></table></div></div></div>
<?php else: ?><div class="panel"><h2>Menu tidak ditemukan</h2><a href="?tab=dashboard">Kembali</a></div><?php endif ?>
</main></div>

<?php else: $sid=(int)$_SESSION['user']['siswa_id'];$q=$pdo->prepare("SELECT s.*,k.nama AS kelas FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.id=?");$q->execute([$sid]);$student=$q->fetch();$q=$pdo->prepare("SELECT n.nilai,n.predikat,n.catatan,m.nama AS mapel,t.nama AS tahun,t.semester FROM nilai n JOIN mata_pelajaran m ON m.id=n.mapel_id JOIN tahun_ajaran t ON t.id=n.tahun_ajaran_id WHERE n.siswa_id=? AND t.aktif=1 ORDER BY m.nama");$q->execute([$sid]);$myGrades=$q->fetchAll(); ?>
<div class="welcome"><h2>Selamat datang, <?=e($student['nama'])?> 👋</h2><p>Berikut hasil belajar pada RaporKu.</p></div>
<div class="panel"><div style="display:flex;justify-content:space-between;flex-wrap:wrap"><div><h2><?=e($student['nama'])?></h2><p>NIS: <?=e($student['nis'])?> · Kelas: <?=e($student['kelas'])?></p></div><div><b>RAPOR SISWA</b><p class="small">Tahun pelajaran aktif</p></div></div><hr style="border:0;border-top:1px solid #eee;margin:18px 0"><h3>Hasil Belajar</h3><div class="table-wrap"><table><tr><th>No</th><th>Mata Pelajaran</th><th>Nilai</th><th>Predikat</th><th>Catatan</th></tr><?php foreach($myGrades as $i=>$g):?><tr><td><?=$i+1?></td><td><?=e($g['mapel'])?></td><td><?=e($g['nilai'])?></td><td><?=e($g['predikat'])?></td><td><?=e($g['catatan'])?></td></tr><?php endforeach?></table></div><?php if(!$myGrades):?><p>Belum ada nilai untuk tahun ajaran aktif.</p><?php endif?></div>
<?php endif ?>
</div></body></html>
