<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
use RuntimeException;
final class Foundation
{
    public const POLICY = ['memory_cost'=>65536, 'time_cost'=>4, 'threads'=>1];
    public function __construct(private array $config) {}
    public function connect(bool $database = true): PDO
    {
        return new PDO('mysql:host='.$this->config['host'].';port=3306;'.($database ? 'dbname='.$this->config['database'].';' : '').'charset=utf8mb4', $this->config['user'], $this->config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    public function setup(): array
    {
        $this->guard();
        $this->capabilities();
        $pdo = $this->connect(false);
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');
        $query->execute([$this->config['database']]);
        if ((int)$query->fetchColumn() > 0) $this->assertIdentity($this->connect());
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$this->config['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->migrateAndSeed();
        return $this->runtime();
    }
    public function guard(): void
    {
        if ($this->config['env'] !== 'local' || $this->config['host'] !== 'db' || !in_array($this->config['database'], ['laporkota_local'], true) || !preg_match('/^laporkota_[a-z0-9_]+$/D', $this->config['project'])) {
            throw new RuntimeException('target rejected');
        }
    }
    public function runtime(): array
    {
        $this->guard();
        $this->capabilities();
        $pdo = $this->connect();
        return ['ready'=>true, 'php'=>PHP_VERSION, 'mysql'=>$pdo->query('SELECT VERSION()')->fetchColumn(), 'database'=>$this->config['database'], 'project'=>$this->config['project'], 'extensions'=>['pdo_mysql','mbstring','fileinfo','gd'], 'argon2id'=>true, 'policy'=>self::POLICY];
    }
    private function migrateAndSeed(): void
    {
        $pdo = $this->connect();
        $sql = file_get_contents(dirname(__DIR__).'/migrations/001-foundation.sql');
        $checksum = hash('sha256', $sql);
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
        $check->execute([$this->config['database'], 'schema_migrations']);
        if ((int)$check->fetchColumn() > 0) {
            $existing = $pdo->query("SELECT checksum FROM schema_migrations WHERE version='001'")->fetchColumn();
            if ($existing !== false && $existing !== $checksum) throw new RuntimeException('migration checksum mismatch');
        }
        $pdo->exec($sql);
        $pdo->prepare('INSERT IGNORE INTO schema_migrations VALUES (?, ?)')->execute(['001', $checksum]);
        $pdo->prepare('INSERT IGNORE INTO local_identity VALUES (1, ?, ?)')->execute([$this->config['project'], $this->config['database']]);
        $this->seed($pdo);
    }
    private function seed(PDO $pdo): void
    {
        $teams = filter_var($this->config['teams'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100]]);
        if ($teams === false) throw new RuntimeException('invalid fixture team count');
        $meta = $pdo->query('SELECT version, teams FROM seed_metadata WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if ($meta) {
            if ($meta['version'] !== 'seed-1' || (int)$meta['teams'] !== $teams) throw new RuntimeException('seed configuration mismatch; explicit local reset required');
            return;
        }
        $fixture = require dirname(__DIR__).'/fixtures/seed-v1.php';
        $rows = $this->fixtureRows($fixture, $teams);
        $user = $pdo->prepare('INSERT INTO users (id, username, password_hash, password_scheme, role, dinas_id) VALUES (?, ?, ?, ?, ?, ?)');
        $dinas = $pdo->prepare('INSERT INTO dinas VALUES (?, ?)');
        $report = $pdo->prepare('INSERT INTO reports VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $history = $pdo->prepare('INSERT INTO report_history (id,report_id,actor_id,from_status,to_status,note,visibility,created_at) VALUES (?,?,?,?,?,?,?,?)');
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT INTO categories VALUES (1,'Infrastruktur dummy'), (2,'Lingkungan dummy')");
            foreach ($rows['dinas'] as $row) $dinas->execute($row);
            foreach ($rows['users'] as [$id, $username, $scheme, , , $role, $dinasId]) {
                $hash = $scheme === 'modern' ? password_hash($fixture['password'], PASSWORD_ARGON2ID, self::POLICY) : hash('sha256', $fixture['legacy_salt'].$fixture['password']);
                $user->execute([$id, $username, $hash, $scheme, $role, $dinasId]);
            }
            foreach ($rows['reports'] as $row) $report->execute($row);
            foreach ($rows['history'] as $row) $history->execute($row);
            $pdo->prepare('INSERT INTO seed_metadata VALUES (1, ?, ?)')->execute([$fixture['version'], $teams]);
            if ($ownsTransaction) $pdo->commit();
        } catch (\Throwable $error) { if ($ownsTransaction) $pdo->rollBack(); throw $error; }
    }
    private function fixtureRows(array $fixture, int $teams): array
    {
        $statuses = ['baru','ditugaskan','diproses','selesai','ditolak'];
        $paths = ['baru'=>['baru'], 'ditugaskan'=>['baru','ditugaskan'], 'diproses'=>['baru','ditugaskan','diproses'], 'selesai'=>['baru','ditugaskan','diproses','selesai'], 'ditolak'=>['baru','ditolak']];
        $extraNotes = ['ditugaskan'=>'Diteruskan ke dinas terkait', 'diproses'=>'Petugas sedang menangani di lapangan', 'selesai'=>'Perbaikan selesai dan sudah diperiksa', 'ditolak'=>'Ditolak: di luar kewenangan pemerintah kota'];
        $extras = $fixture['extra_reports'];
        $start = new \DateTimeImmutable($fixture['created_at'], new \DateTimeZone('UTC'));
        $users = [[1,'admin_kota','modern',1,1,'admin',null]];
        $dinas = $reports = $history = [];
        foreach (range(1, $teams) as $team) {
            $base = $team*100;
            foreach ([1,2] as $d) $dinas[] = [$base+$d, 'Dinas Dummy '.$team.'-'.$d];
            foreach ([1=>['modern','warga',null], 2=>['legacy_sha256','warga',null], 3=>['modern','petugas',$base+1], 4=>['modern','petugas',$base+2], 5=>['modern','warga',null], 6=>['modern','warga',null], 7=>['modern','warga',null]] as $n=>[$scheme,$role,$dinasId]) {
                $users[] = [$base+$n, 'tim'.$team.'_'.$role.'_'.$n, $scheme, 1, 1, $role, $dinasId];
            }
            $teamReports = [];
            foreach ($statuses as $n=>$status) {
                $teamReports[] = ['id'=>$base+$n+1, 'author'=>$base+1+($n%2), 'title'=>$fixture['titles'][$n], 'description'=>"Deskripsi dummy O'Brian <b>rusak</b> 100%_雪", 'category'=>1+($n%2), 'location'=>'Lokasi fiktif '.$team, 'dinas'=>in_array($status, ['baru','ditolak'], true) ? null : $base+1+($n%2), 'status'=>$status, 'created_at'=>$fixture['created_at'], 'extra'=>false];
            }
            foreach (array_keys($extras) as $k) {
                [$title, $description, $location] = $extras[($k + 7*($team-1)) % count($extras)];
                $status = $statuses[($k*3) % 5];
                $teamReports[] = ['id'=>$base+6+$k, 'author'=>$base+5+($k%3), 'title'=>$title, 'description'=>$description, 'category'=>1+($k%2), 'location'=>$location, 'dinas'=>in_array($status, ['baru','ditolak'], true) ? null : $base+1+($k%2), 'status'=>$status, 'created_at'=>$start->modify('+'.($k+1).' days +'.(($k*7)%24).' hours')->format('Y-m-d H:i:s'), 'extra'=>true];
            }
            foreach ($teamReports as $r) {
                $reports[] = [$r['id'],$r['author'],$r['title'],$r['description'],$r['category'],$r['location'],$r['dinas'],$r['status'],$r['created_at']];
                $previous = null;
                foreach ($paths[$r['status']] as $step=>$next) {
                    $actor = $next === 'baru' ? $r['author'] : (in_array($next,['ditugaskan','ditolak'],true) ? 1 : $base+($r['dinas'] === $base+1 ? 3 : 4));
                    if ($r['extra']) {
                        $note = $next === 'baru' ? 'Aduan diterima' : $extraNotes[$next];
                        $at = (new \DateTimeImmutable($r['created_at'], new \DateTimeZone('UTC')))->modify('+'.($step*5).' hours')->format('Y-m-d H:i:s');
                    } else {
                        $note = $next === 'ditolak' ? 'Ditolak: duplikat dummy' : ($next === 'selesai' ? 'Tindak lanjut dummy telah selesai' : 'Status dummy '.$next);
                        $at = $fixture['created_at'];
                    }
                    $history[] = [$r['id']*10+$step,$r['id'],$actor,$previous,$next,$note,'warga',$at];
                    $previous = $next;
                }
            }
        }
        return ['users'=>$users, 'dinas'=>$dinas, 'reports'=>$reports, 'history'=>$history];
    }
    public function manifest(): array
    {
        $this->guard();
        $pdo = $this->connect();
        $meta = $pdo->query('SELECT version, teams FROM seed_metadata WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $counts = [];
        foreach (['users','dinas','categories','reports','report_history','attachments'] as $table) $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        $statuses = [];
        foreach (['baru','ditugaskan','diproses','selesai','ditolak'] as $status) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE status=?'); $query->execute([$status]); $statuses[$status]=(int)$query->fetchColumn();
        }
        return ['seed_version'=>$meta['version'], 'migration_version'=>$pdo->query('SELECT version FROM schema_migrations')->fetchColumn(), 'teams'=>(int)$meta['teams'], 'counts'=>$counts, 'statuses'=>$statuses];
    }
    public function resetLocal(): array
    {
        $this->guard();
        $this->runtime();
        $pdo = $this->connect();
        $this->assertIdentity($pdo);
        $pdo->beginTransaction();
        try {
            foreach (['attachments','report_history','reports','users','dinas','categories','rate_limit_state','login_audit','seed_metadata'] as $table) $pdo->exec('DELETE FROM '.$table);
            $this->seed($pdo);
            $pdo->commit();
        } catch (\Throwable $error) { $pdo->rollBack(); throw $error; }
        return ['reset'=>true, 'database'=>$this->config['database'], 'project'=>$this->config['project'], 'manifest'=>$this->manifest()];
    }
    private function assertIdentity(PDO $pdo): void
    {
        try {
            $rows = $pdo->query('SELECT project, database_name FROM local_identity WHERE id=1')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $error) { throw new RuntimeException('target rejected'); }
        if (count($rows) !== 1 || $rows[0]['project'] !== $this->config['project'] || $rows[0]['database_name'] !== $this->config['database'] || $pdo->query('SELECT DATABASE()')->fetchColumn() !== $this->config['database']) throw new RuntimeException('target rejected');
    }
    private function capabilities(): void
    {
        foreach (['pdo_mysql','mbstring','fileinfo','gd'] as $extension) {
            if (!extension_loaded($extension)) throw new RuntimeException('required extension unavailable');
        }
        foreach (['password_algos','password_hash','password_verify','password_needs_rehash'] as $api) if (!function_exists($api)) throw new RuntimeException('Argon2id unavailable');
        if (!in_array('argon2id', password_algos(), true)) throw new RuntimeException('Argon2id unavailable');
        $hash = password_hash('runtime-dummy-probe', PASSWORD_ARGON2ID, self::POLICY);
        if (!password_verify('runtime-dummy-probe', $hash) || password_needs_rehash($hash, PASSWORD_ARGON2ID, self::POLICY)) throw new RuntimeException('Argon2id unavailable');
    }
}
