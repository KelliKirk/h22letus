<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Uue hääletuse algatamine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_voting'])) {
    try {
        // Kasuta protseduur uue hääletuse alustamiseks
        $stmt = $pdo->prepare("CALL AlgataHaaletus()");
        $stmt->execute();
        
        $message = 'Uus hääletus on edukalt algatatud!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Viga hääletuse algatamisel: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Hangi kõik hääletused
$allVotings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM TULEMUSED ORDER BY tulemuse_id DESC");
    $stmt->execute();
    $allVotings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Viga andmete laadimisel: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hääletuse haldamine - E-hääletussüsteem</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Hääletuse haldamine</h1>
            <p>Uute hääletuste algatamine ja tulemuste vaatamine</p>
        </div>
        
        <div class="content">
            <!-- Navigatsiooni menüü -->
            <div class="nav-menu">
                <a href="index.php" class="nav-btn">🗳️ Hääletamine</a>
                <a href="start_voting.php" class="nav-btn active">⚙️ Haldamine</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Uue hääletuse algatamine -->
            <div class="voting-form">
                <h2>🚀 Algata uus hääletus</h2>
                <p>Vajutades nupule alustatakse uut 5-minutilist hääletust. Eelmine hääletus märgitakse automaatselt mitteaktiivseks.</p>
                <form method="POST" action="">
                    <button type="submit" name="start_voting" class="btn-primary" 
                            onclick="return confirm('Kas olete kindel, et soovite algatada uue hääletuse?')">
                        🚀 Algata uus hääletus
                    </button>
                </form>
            </div>

            <!-- Hääletuste ajalugu -->
            <?php if (!empty($allVotings)): ?>
            <div class="voting-history">
                <h3>📊 Hääletuste ajalugu</h3>
                <div class="table-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Alguse aeg</th>
                                <th>Hääletanud</th>
                                <th>Poolt</th>
                                <th>Vastu</th>
                                <th>Staatus</th>
                                <th>Tulemus</th>
                                <th>Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allVotings as $voting): ?>
                            <?php
                                // Kasuta config.php funktsiooni
                                $isExpired = isVotingExpired($voting['tulemuse_id']);
                                $timeLeft = getTimeLeft($voting['tulemuse_id']);
                                
                                $result = '';
                                if ($voting['poolt_haali'] > $voting['vastu_haali']) {
                                    $result = '✅ Poolt võitis';
                                } elseif ($voting['vastu_haali'] > $voting['poolt_haali']) {
                                    $result = '❌ Vastu võitis';
                                } elseif ($voting['haaletanute_arv'] > 0) {
                                    $result = '⚖️ Viik';
                                } else {
                                    $result = '⏳ Pole hääletatud';
                                }
                                
                                // Arvuta staatuse info
                                $statusInfo = '';
                                if ($voting['on_aktiivne'] && !$isExpired && $timeLeft > 0) {
                                    $minutes = floor($timeLeft / 60);
                                    $seconds = $timeLeft % 60;
                                    $statusInfo = sprintf("Jäänud: %02d:%02d", $minutes, $seconds);
                                } elseif ($voting['on_aktiivne'] && $isExpired) {
                                    $statusInfo = "Ametlik periood lõppenud";
                                }
                            ?>
                            <tr>
                                <td data-label="ID"><?php echo $voting['tulemuse_id']; ?></td>
                                <td data-label="Alguse aeg"><?php echo date('d.m H:i', strtotime($voting['h_alguse_aeg'])); ?></td>
                                <td data-label="Hääletanud"><?php echo $voting['haaletanute_arv']; ?>/11</td>
                                <td data-label="Poolt" class="vote-poolt"><?php echo $voting['poolt_haali']; ?></td>
                                <td data-label="Vastu" class="vote-vastu"><?php echo $voting['vastu_haali']; ?></td>
                                <td data-label="Staatus">
                                    <?php if ($voting['on_aktiivne']): ?>
                                        <?php if ($isExpired): ?>
                                            🟡 Aktiivne (mitteametlik)
                                        <?php else: ?>
                                            🟢 Aktiivne (ametlik)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        ⚫ Mitteaktiivne
                                    <?php endif; ?>
                                </td>
                                <td data-label="Tulemus"><?php echo $result; ?></td>
                                <td data-label="Info"><small><?php echo $statusInfo; ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Detailne hääletuse info (aktiivse hääletuse jaoks) -->
            <?php 
            $activeVoting = getActiveVoting();
            if ($activeVoting): 
                $votingInfo = getVotingWithTimeInfo($activeVoting['tulemuse_id']);
                if ($votingInfo):
            ?>
            <div class="voting-history">
                <h3>📋 Aktiivse hääletuse detailid (ID: <?php echo $activeVoting['tulemuse_id']; ?>)</h3>
                
                <!-- Ametlike ja mitteametlike häälte statistika -->
                <?php
                $stmt = $pdo->prepare("SELECT * FROM HAALETUS WHERE tulemuse_id = ? ORDER BY haaletuse_aeg ASC");
                $stmt->execute([$activeVoting['tulemuse_id']]);
                $allVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $officialVotes = 0;
                $unofficialVotes = 0;
                $officialPoolt = 0;
                $officialVastu = 0;
                $unofficialPoolt = 0;
                $unofficialVastu = 0;
                
                foreach ($allVotes as $vote) {
                    if (isVoteOfficial($vote['haaletuse_aeg'], $activeVoting['h_alguse_aeg'])) {
                        $officialVotes++;
                        if ($vote['otsus'] === 'poolt') $officialPoolt++;
                        else $officialVastu++;
                    } else {
                        $unofficialVotes++;
                        if ($vote['otsus'] === 'poolt') $unofficialPoolt++;
                        else $unofficialVastu++;
                    }
                }
                ?>
                
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-number"><?php echo $officialVotes; ?></div>
                        <div class="status-label">Ametlikud hääled</div>
                    </div>
                    <div class="status-item">
                        <div class="status-number vote-poolt"><?php echo $officialPoolt; ?></div>
                        <div class="status-label">Ametlik poolt</div>
                    </div>
                    <div class="status-item">
                        <div class="status-number vote-vastu"><?php echo $officialVastu; ?></div>
                        <div class="status-label">Ametlik vastu</div>
                    </div>
                    <?php if ($unofficialVotes > 0): ?>
                    <div class="status-item">
                        <div class="status-number" style="opacity: 0.6;"><?php echo $unofficialVotes; ?></div>
                        <div class="status-label">Mitteametlikud</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Kõigi häälte detailne nimekiri -->
                <?php if (!empty($allVotes)): ?>
                <div class="table-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Nimi</th>
                                <th>Hääl</th>
                                <th>Aeg</th>
                                <th>Staatus</th>
                                <th>Mõju tulemusele</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allVotes as $vote): ?>
                            <?php $isOfficial = isVoteOfficial($vote['haaletuse_aeg'], $activeVoting['h_alguse_aeg']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vote['eesnimi'] . ' ' . $vote['perenimi']); ?></td>
                                <td class="vote-<?php echo $vote['otsus']; ?>">
                                    <?php echo $vote['otsus'] === 'poolt' ? '👍 Poolt' : '👎 Vastu'; ?>
                                </td>
                                <td><?php echo date('H:i:s', strtotime($vote['haaletuse_aeg'])); ?></td>
                                <td>
                                    <?php if ($isOfficial): ?>
                                        <span class="status-official">✅ Ametlik</span>
                                    <?php else: ?>
                                        <span class="status-unofficial">⚠️ Mitteametlik</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isOfficial): ?>
                                        <span style="color: green;">Mõjutab tulemust</span>
                                    <?php else: ?>
                                        <span style="color: orange;">Ei mõjuta tulemust</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; endif; ?>

            <!-- Logi vaatamine -->
            <div class="voting-history">
                <h3>📋 Süsteemi logi (viimased 20 kirjet)</h3>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM LOGI ORDER BY tegevuse_aeg DESC LIMIT 20");
                    $stmt->execute();
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($logs)):
                ?>
                <div class="table-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Aeg</th>
                                <th>Tegevus</th>
                                <th>Kasutaja</th>
                                <th>Otsus</th>
                                <th>Lisainfo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('H:i:s', strtotime($log['tegevuse_aeg'])); ?></td>
                                <td><?php echo htmlspecialchars($log['tegevuse_tyyp']); ?></td>
                                <td><?php echo htmlspecialchars($log['kasutaja'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($log['otsus']): ?>
                                        <span class="vote-<?php echo $log['otsus']; ?>">
                                            <?php echo $log['otsus'] === 'poolt' ? '👍' : '👎'; ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['lisainfo'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p>Logi kirjeid ei leitud.</p>
                <?php endif; ?>
                <?php
                } catch (PDOException $e) {
                    echo '<p>Viga logi laadimisel: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Automaatne uuendamine iga 30 sekundi järel
        setInterval(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>