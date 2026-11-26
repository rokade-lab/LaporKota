<?php
declare(strict_types=1);
namespace LaporKota;
final class View
{
    public static function e(string|int|null $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function page(string $title,string $content,?array $user=null,bool $reportPage=false):void
    {
        header('Content-Type: text/html; charset=utf-8');
        $nav='<a href="/login">'.self::icon('login').'Masuk</a><a href="/register">'.self::icon('user-plus').'Daftar warga</a>';
        if($user){
            $home=match($user['role']){'admin'=>'/admin/laporan','petugas'=>'/petugas/laporan',default=>'/laporan'};
            $nav='<a href="'.$home.'">'.self::icon('list').'Aduan</a>';
            if($user['role']==='warga')$nav.='<a class="button small" href="/laporan/buat">'.self::icon('plus').'Buat aduan</a>';
            if($user['role']==='admin')$nav.='<a href="/admin/petugas">'.self::icon('users').'Kelola petugas</a>';
            $nav.='<form method="post" action="/logout">'.self::csrf().'<button class="quiet">'.self::icon('logout').'Keluar</button></form>';
        }
        $role=$user?match($user['role']){'admin'=>'Admin layanan','petugas'=>'Petugas dinas',default=>'Warga'}:'Layanan publik digital';
        $who=$user?'<span class="civic-user">'.self::icon('user').self::e($user['username']).'</span>':'<span class="civic-caption">Portal layanan masyarakat</span>';
        $head='<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0e5a4a"><link rel="icon" type="image/svg+xml" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/app.css"><title>'.self::e($title).' · LaporKota</title></head><body><a class="skip" href="#utama">Lewati ke isi</a><div class="civic-bar"><div class="container"><span class="civic-dot"></span><span>Pemerintah Kota Arunika</span>'.$who.'</div></div><header class="site-header"><div class="container site-header-inner"><a class="brand" href="/laporan"><span class="brand-mark" aria-hidden="true">LK</span><span>LaporKota<small>Ruang warga, kota terjaga.</small></span></a><nav aria-label="Navigasi utama">'.$nav.'</nav></div></header>';
        if(!$user&&in_array($title,['Masuk','Daftar warga'],true)){
            $register=$title==='Daftar warga';
            $main='<main id="utama" class="container auth-layout"><section class="auth-visual"><div class="auth-visual-body"><p class="eyebrow">Kota Arunika</p><h2>Laporkan, pantau, dan lihat kota Anda berbenah.</h2><ul class="auth-points"><li>'.self::icon('pin').'<span><strong>Sampaikan dari mana saja</strong>Jalan rusak, lampu mati, sampah menumpuk, cukup dari ponsel.</span></li><li>'.self::icon('route').'<span><strong>Status yang transparan</strong>Setiap tahap verifikasi dan penanganan tercatat jelas.</span></li><li>'.self::icon('shield').'<span><strong>Privasi terjaga</strong>Aduan hanya dibaca oleh petugas yang berwenang.</span></li></ul></div><p class="photo-credit">Foto: Jalan Cihapit · RasyaAbhirama13, CC BY-SA 4.0</p></section><section class="auth-card"><p class="eyebrow">'.self::e($role).'</p><h1>'.self::e($title).'</h1><p class="lead">'.($register?'Buat akun warga untuk mengirim dan memantau aduan Anda.':'Gunakan akun LaporKota Anda untuk melanjutkan.').'</p>'.$content.'<p class="auth-switch">'.($register?'Sudah punya akun? <a href="/login">Kembali ke halaman login</a>':'Belum punya akun? <a href="/register">Buat akun warga baru</a>').'</p></section></main>';
        }else{
            $main='<section class="hero"><div class="container hero-inner"><div class="hero-body"><p class="eyebrow">'.self::e($role).'</p><h1>'.($reportPage?$title:self::e($title)).'</h1><p class="lead">Sampaikan kebutuhan lingkungan Anda. Pantau setiap langkah penanganannya di satu tempat.</p><ul class="hero-chips"><li>'.self::icon('route').'Status terpantau</li><li>'.self::icon('shield').'Privasi terjaga</li><li>'.self::icon('clock').'Tindak lanjut tercatat</li></ul></div><span class="service-seal" aria-hidden="true">A<br><small>ARUNIKA</small></span></div></section><main id="utama" class="container"><div class="workspace"><section class="content-panel">'.$content.'</section><aside class="help-panel"><figure class="help-photo"><img src="/assets/img/taman-kota.jpg" alt="Jalur pejalan kaki di taman kota yang rindang" width="720" height="405" loading="lazy"><figcaption>Taman Bungkul · consigliere ivan, CC BY 2.0</figcaption></figure><div class="help-body"><p class="eyebrow">Bersama merawat kota</p><h2>Suara Anda berarti.</h2><p>Laporan yang jelas membantu dinas memberikan tindak lanjut yang tepat.</p><ol><li><strong>Ceritakan kondisinya</strong><span>Tambahkan lokasi dan foto bila diperlukan.</span></li><li><strong>Pantau perkembangannya</strong><span>Periksa status dan riwayat penanganan.</span></li><li><strong>Terima tindak lanjut</strong><span>Catatan petugas tersedia pada aduan Anda.</span></li></ol><div class="privacy-note">'.self::icon('shield').'<span>Aduan bersifat pribadi dan hanya dapat dibaca oleh pihak yang berwenang.</span></div></div></aside></div></main>';
        }
        echo $head.$main.'<footer class="site-footer"><div class="container footer-grid"><div><strong class="footer-brand"><span class="brand-mark" aria-hidden="true">LK</span>LaporKota</strong><p>Layanan masyarakat Kota Arunika · 2026</p></div><div><p class="footer-title">Jam layanan</p><p>Senin–Jumat, 08.00–16.00 WIB.<br>Aduan daring dapat dikirim kapan saja.</p></div><div><p class="footer-title">Kredit foto</p><p>Wikimedia Commons: <a href="https://commons.wikimedia.org/wiki/File:Jakarta_skyline_at_dusk_from_Central_Jakarta,_Indonesia.jpg" rel="noopener">Secretdriver</a> (CC BY-SA 4.0), <a href="https://commons.wikimedia.org/wiki/File:Street_outside_Cihapit_market,_Bandung_(2).jpg" rel="noopener">RasyaAbhirama13</a> (CC BY-SA 4.0), <a href="https://commons.wikimedia.org/wiki/File:Taman_Bungkul_2019-1-05.jpg" rel="noopener">consigliere ivan</a> (CC BY 2.0).</p></div></div></footer></body></html>';
    }
    private static function icon(string $name):string
    {
        $paths=['pin'=>'<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>','route'=>'<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7.5a3.5 3.5 0 0 0 0-7h-7a3.5 3.5 0 0 1 0-7H16"/>','shield'=>'<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>','clock'=>'<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>','user'=>'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.2-3.6 3.8-5.5 7-5.5s5.8 1.9 7 5.5"/>','tag'=>'<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>','photo'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 8"/>','list'=>'<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','users'=>'<circle cx="9" cy="8" r="3.2"/><path d="M3 19.5c.9-3.2 3.2-5 6-5s5.1 1.8 6 5"/><path d="M15.5 5.2a3 3 0 0 1 0 5.6M17.5 14.8c1.7.6 2.9 2.2 3.5 4.7"/>','logout'=>'<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/>','login'=>'<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 8l4 4-4 4M14 12H4"/>','user-plus'=>'<circle cx="10" cy="8" r="3.5"/><path d="M3 20c1.2-3.6 3.8-5.5 7-5.5 1.5 0 2.9.4 4 1.2"/><path d="M18 14v6M15 17h6"/>','search'=>'<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>','send'=>'<path d="M21 3L10 14"/><path d="M21 3l-7 18-4-7-7-4z"/>'];
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$paths[$name].'</svg>';
    }
    public static function csrf():string{return '<input type="hidden" name="csrf" value="'.self::e($_SESSION['csrf']).'">';}
    public static function authForm(bool $register,string $error=''):string
    {
        return ($error!==''?'<p role="alert">'.self::e($error).'</p>':'').'<form class="stack" method="post">'.self::csrf().'<label>Nama pengguna<input name="username" autocomplete="username" maxlength="100" required></label><label>Kata sandi<input type="password" name="password" autocomplete="'.($register?'new-password':'current-password').'" '.($register?'minlength="12"':'').' required></label><button>'.($register?'Daftar':'Masuk').'</button></form>';
    }
    public static function officerForm(array $dinas):string
    {
        $options='';foreach($dinas as $d)$options.='<option value="'.self::e($d['id']).'">'.self::e($d['name']).'</option>';
        return '<form class="stack" method="post">'.self::csrf().'<label>Nama pengguna<input name="username" required minlength="3" maxlength="100" autocomplete="off"></label><label>Kata sandi<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Dinas<select name="dinas_id">'.$options.'</select></label><button>Buat petugas</button></form>';
    }
    public static function createForm(array $categories):string
    {
        $options='';foreach($categories as $c)$options.='<option value="'.self::e($c['id']).'">'.self::e($c['name']).'</option>';
        return '<form class="stack" method="post" enctype="multipart/form-data">'.self::csrf().'<label>Judul aduan<input name="title" maxlength="200" required></label><label>Deskripsi<textarea name="description" maxlength="5000" required></textarea></label><label>Kategori<select name="category_id">'.$options.'</select></label><label>Lokasi<input name="location_text" maxlength="255" required></label><label>Foto pendukung (opsional)<input type="file" name="photos[]" accept="image/jpeg,image/png" multiple></label><p>Maksimal tiga foto JPEG/PNG, masing-masing 2 MiB.</p><button>'.self::icon('send').'Kirim aduan</button></form>';
    }
    public static function listing(array $reports,array $input):string
    {
        $content='<form class="filters" method="get"><label>Cari judul<input name="q" value="'.self::e($input['q']??'').'" maxlength="200"></label><label>Status<select name="status" aria-label="Status"><option value="">Semua status</option>';
        foreach(['baru','ditugaskan','diproses','selesai','ditolak'] as $status)$content.='<option value="'.$status.'"'.(($input['status']??'')===$status?' selected':'').'>'.$status.'</option>';
        $content.='</select></label><label>Urutkan<select name="sort" aria-label="Urutkan"><option value="created_at">Waktu dibuat</option><option value="status"'.(($input['sort']??'')==='status'?' selected':'').'>Status</option></select></label><label>Arah<select name="direction" aria-label="Arah"><option value="desc">Terbaru dahulu</option><option value="asc"'.(($input['direction']??'')==='asc'?' selected':'').'>Terlama dahulu</option></select></label><button>'.self::icon('search').'Cari aduan</button></form>';
        if(!$reports)return $content.'<section class="empty">'.self::icon('route').'<h2>Belum ada aduan</h2><p>Aduan yang sesuai pencarian akan tampil di sini.</p></section>';
        $content.='<div class="report-list">';foreach($reports as $r)$content.='<article class="report-card status-'.self::e($r['status']).'"><p class="status status-'.self::e($r['status']).'">'.self::e($r['status']).'</p><h2><a href="/laporan/detail?id='.self::e($r['id']).'">'.$r['title'].'</a></h2><p class="report-meta">'.self::icon('pin').self::e($r['location_text']).'</p><time class="report-meta">'.self::icon('clock').self::e($r['created_at']).'</time></article>';
        return $content.'</div>';
    }
    public static function detail(array $report,?array $user=null,array $dinas=[]):string
    {
        $content='<p class="status status-'.self::e($report['status']).'">'.self::e($report['status']).'</p><p class="detail-description">'.$report['description'].'</p><div class="detail-meta"><p>'.self::icon('pin').'Lokasi: '.self::e($report['location_text']).'</p><p>'.self::icon('tag').'Kategori: '.self::e($report['category_name']).'</p></div><h2>Riwayat penanganan</h2><ol class="timeline">';
        foreach($report['history'] as $h)$content.='<li class="status-'.self::e($h['to_status']).'"><strong>'.self::e($h['to_status']).'</strong><p>'.self::e($h['note']).'</p><small>'.self::e($h['actor']).' · '.self::e($h['created_at']).'</small></li>';
        $content.='</ol><h2>Foto pendukung</h2><div class="photos">';foreach($report['attachments'] as $a)$content.='<a href="/lampiran?id='.self::e($a['id']).'"><img src="/lampiran?id='.self::e($a['id']).'" alt="Foto pendukung aduan" loading="lazy"></a>';
        return $content.'</div>'.($report['attachments']?'':'<p class="photos-empty">'.self::icon('photo').'Tidak ada foto pendukung pada aduan ini.</p>').self::workflow($report,$user,$dinas);
    }
    private static function workflow(array $report,?array $user,array $dinas):string
    {
        if(!$user)return '';
        $id='<input type="hidden" name="id" value="'.self::e($report['id']).'">';
        if($user['role']==='admin'&&$report['status']==='baru'){
            $options='';foreach($dinas as $d)$options.='<option value="'.self::e($d['id']).'">'.self::e($d['name']).'</option>';
            return '<section class="workflow"><h2>Verifikasi aduan</h2><form method="post" action="/admin/laporan/tugaskan">'.self::csrf().$id.'<label>Dinas tujuan<select name="dinas_id">'.$options.'</select></label><label>Catatan internal penugasan<textarea name="internal_note" maxlength="5000"></textarea></label><button>Verifikasi dan tugaskan</button></form><form method="post" action="/admin/laporan/tolak">'.self::csrf().$id.'<label>Alasan penolakan untuk warga<textarea name="note" required maxlength="5000"></textarea></label><label>Catatan internal penolakan<textarea name="internal_note" maxlength="5000"></textarea></label><button>Tolak aduan</button></form></section>';
        }
        if($user['role']==='petugas'&&in_array($report['status'],['ditugaskan','diproses'],true)){
            $complete=$report['status']==='diproses';
            return '<section class="workflow"><h2>Tindak lanjut dinas</h2><form method="post" action="/petugas/laporan/status">'.self::csrf().$id.'<input type="hidden" name="status" value="'.($complete?'selesai':'diproses').'">'.($complete?'<label>Catatan tindak lanjut untuk warga<textarea name="note" required maxlength="5000"></textarea></label>':'').'<label>Catatan internal '.($complete?'penyelesaian':'penanganan').'<textarea name="internal_note" maxlength="5000"></textarea></label><button>'.($complete?'Selesaikan aduan':'Mulai penanganan').'</button></form></section>';
        }
        return '';
    }
    public static function pager(array $result,array $input):string
    {
        if($result['total']===0)return '';
        $query=[];foreach(['q','status','sort','direction'] as $key)if(isset($input[$key])&&is_string($input[$key])&&$input[$key]!=='')$query[$key]=$input[$key];
        $link=static fn(int $page,string $label,string $rel):string=>'<a class="button quiet" rel="'.$rel.'" href="?'.self::e(http_build_query($query+['page'=>$page])).'">'.$label.'</a>';
        $html='<nav class="pager" aria-label="Halaman aduan">'.($result['page']>1?$link(min($result['page']-1,$result['pages']),'Sebelumnya','prev'):'<span></span>');
        $html.='<span>Halaman '.self::e($result['page']).' dari '.self::e($result['pages']).' · '.self::e($result['total']).' aduan</span>';
        return $html.($result['page']<$result['pages']?$link($result['page']+1,'Berikutnya','next'):'<span></span>').'</nav>';
    }
}
