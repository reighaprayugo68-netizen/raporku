<?php
require_once 'config.php';

$pdo->exec("INSERT IGNORE INTO kelas (nama, tingkat, keterangan) VALUES
('Kelas 7',7,'SMP Kelas VII'),('Kelas 8',8,'SMP Kelas VIII'),('Kelas 9',9,'SMP Kelas IX')");

$pdo->exec("INSERT IGNORE INTO tahun_ajaran (nama, semester, aktif) VALUES ('2026/2027','Ganjil',1)");

$subjects = ['Bahasa Indonesia','Matematika','IPA','IPS','Bahasa Inggris','PPKn','Informatika'];
$stmt = $pdo->prepare("INSERT IGNORE INTO mata_pelajaran (nama) VALUES (?)");
foreach ($subjects as $s) $stmt->execute([$s]);

$students = [
  7 => [['Ahmad Fauzan','7001'],['Siti Aisyah','7002'],['Rizky Pratama','7003'],['Dinda Putri','7004'],['Fajar Ramadhan','7005']],
  8 => [['Andi Saputra','8001'],['Nabila Putri','8002'],['Bima Prakoso','8003'],['Citra Lestari','8004'],['Doni Kurniawan','8005']],
  9 => [['Raka Wijaya','9001'],['Aulia Rahma','9002'],['Bagas Maulana','9003'],['Nisa Amalia','9004'],['Yoga Pratama','9005']]
];

$insertStudent = $pdo->prepare("INSERT IGNORE INTO siswa (nis,nama,kelas_id) VALUES (?,?,?)");
$insertUser = $pdo->prepare("INSERT IGNORE INTO users (username,password,role,siswa_id) VALUES (?,?,'siswa',?)");
$findStudent = $pdo->prepare("SELECT id FROM siswa WHERE nis=?");
$findClass = $pdo->prepare("SELECT id FROM kelas WHERE tingkat=?");
foreach ($students as $grade => $list) {
    $findClass->execute([$grade]); $classId = $findClass->fetchColumn();
    foreach ($list as [$name,$nis]) {
        $insertStudent->execute([$nis,$name,$classId]);
        $findStudent->execute([$nis]); $studentId = $findStudent->fetchColumn();
        $insertUser->execute([$nis,password_hash('12345', PASSWORD_DEFAULT),$studentId]);
    }
}

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if (!$adminCount) {
    $stmt = $pdo->prepare("INSERT INTO users (username,password,role) VALUES (?,?, 'admin')");
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
}

$yearId = (int)$pdo->query("SELECT id FROM tahun_ajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
$mapels = $pdo->query("SELECT id,nama FROM mata_pelajaran ORDER BY id")->fetchAll();
$allStudents = $pdo->query("SELECT id,nis,kelas_id FROM siswa")->fetchAll();
$check = $pdo->prepare("SELECT COUNT(*) FROM nilai WHERE siswa_id=? AND mapel_id=? AND tahun_ajaran_id=?");
$insertGrade = $pdo->prepare("INSERT IGNORE INTO nilai (siswa_id,mapel_id,tahun_ajaran_id,nilai,predikat) VALUES (?,?,?,?,?)");
foreach ($allStudents as $st) {
    $seed = (int)substr($st['nis'],-2);
    foreach ($mapels as $i => $m) {
        $score = 75 + (($seed * 3 + $i * 7 + (int)$st['kelas_id']) % 21);
        $grade = $score >= 90 ? 'A' : ($score >= 85 ? 'B+' : ($score >= 80 ? 'B' : 'C'));
        $insertGrade->execute([$st['id'],$m['id'],$yearId,$score,$grade]);
    }
}
?>
<!doctype html><html lang="id"><meta charset="utf-8"><title>Setup RaporKu</title>
<style>body{font:16px Arial;background:#f4f7fb;padding:40px;color:#17324d}.box{max-width:650px;margin:auto;background:white;padding:30px;border-radius:15px}</style>
<div class="box"><h1>Setup RaporKu berhasil!</h1>
<p>Database awal telah diisi dengan data demo dari website.</p>
<ul><li>Admin: <b>admin</b> / <b>admin123</b></li><li>Akun siswa: username = NIS, password = <b>12345</b></li></ul>
<p><a href="index.php">Buka website RaporKu</a></p>
<p><b>Penting:</b> Hapus atau ubah nama file setup.php setelah instalasi selesai.</p></div>
