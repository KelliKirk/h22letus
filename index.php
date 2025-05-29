<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Hääletuse töötlemine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eesnimi = trim($_POST['eesnimi'] ?? '');
    $perenimi = trim($_POST['perenimi'] ?? '');
    $otsus = $_POST['otsus'] ?? '';
    
    if (empty($eesnimi) || empty($perenimi) || empty($otsus)) {
        $message = 'Palun täida kõik väljad!';
        $messageType = 'error';
    } else {
        try {
            // Kasuta protseduur hääle andmiseks
            $stmt = $pdo->prepare("CALL AnnaHaal(?, ?, ?)");
            $stmt->execute([$eesnimi, $perenimi, $otsus]);
            
            // Kontrolli, kas hääletus on lõppenud
            $activeVoting = getActiveVoting();
            $isExpired = false;
            if ($activeVoting) {
                $isExpired = isVotingExpired($activeVoting['tulemuse_id']);
            }
            
            if ($isExpired) {
                $message = 'Hääl on registreeritud, kuid ei mõjuta tulemust (hääletus on lõppenud)!';
                $messageType = 'warning';
            } else {
                $message = 'Hääletus salvestatud edukalt!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Viga hääletamisel: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Hangi aktiivne hääletus
$activeVoting = getActiveVoting();

// Hangi hääletuse statistika - PARANDATUD
$stats = null;
$timeLeft = 0;
$isExpired = true;

if ($activeVoting) {
    // Kasuta uut funktsiooni
    $votingInfo = getVotingWithTimeInfo($activeVoting['tulemuse_id']);
    
    if ($votingInfo) {
        $stats = $votingInfo['voting'];
        $timeLeft = $votingInfo['timeLeft'];
        $isExpired = $votingInfo['isExpired'];
        
        // Debug info
        error_log("=== INDEX.PHP DEBUG ===");
        error_log("Hääletus ID: " . $activeVoting['tulemuse_id']);
        error_log("Alguse aeg: " . $stats['h_alguse_aeg']);
        error_log("Aega jäänud: " . $timeLeft . " sekundit");
        error_log("Kas lõppenud: " . ($isExpired ? 'jah' : 'ei'));
    }
}

// Hangi hääletuse ajalugu
$votingHistory = [];
if ($activeVoting) {
    $stmt = $pdo->prepare("SELECT * FROM HAALETUS WHERE tulemuse_id = ? ORDER BY haaletuse_aeg DESC");
    $stmt->execute([$activeVoting['tulemuse_id']]);
    $votingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-hääletussüsteem</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗳️ E-hääletussüsteem</h1>
            <p>Digitaalne hääletusplatvorm korrektse ja läbipaistva hääletusprotsessi jaoks</p>
        </div>
        
        <div class="content">
            <!-- Navigatsiooni menüü -->
            <div class="nav-menu">
                <a href="index.php" class="nav-btn active">🗳️ Hääletamine</a>
                <a href="start_voting.php" class="nav-btn">⚙️ Haldamine</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($activeVoting && $stats): ?>
                <!-- Timer - PARANDATUD -->
                <div class="timer <?php echo $isExpired ? 'expired' : ''; ?>" id="timer">
                    <?php if ($isExpired): ?>
                        ⏰ Ametlik hääletus on lõppenud! (Saad edasi hääletada, kuid see ei mõjuta tulemust)
                    <?php else: ?>
                        ⏱️ Aega allesjäänud: <span id="countdown">
                            <?php
                                $minutes = floor($timeLeft / 60);
                                $seconds = $timeLeft % 60;
                                printf("%02d:%02d", $minutes, $seconds);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Hoiatus lõppenud hääletuse kohta -->
                <?php if ($isExpired): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Tähelepanu:</strong> Ametlik hääletusperiood (5 minutit) on lõppenud! 
                    Saad endiselt hääletada, kuid see ei muuda enam ametlikke tulemusi ega kellegi otsust.
                </div>
                <?php endif; ?>
                
                <!-- Hääletuse seis -->
                <div class="voting-status">
                    <h2>📊 Hääletuse seis<?php echo $isExpired ? ' (Lõplik)' : ''; ?></h2>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-number"><?php echo $stats['haaletanute_arv']; ?></div>
                            <div class="status-label">Hääletanud</div>
                        </div>
                        <div class="status-item">
                            <div class="status-number vote-poolt"><?php echo $stats['poolt_haali']; ?></div>
                            <div class="status-label">Poolt</div>
                        </div>
                        <div class="status-item">
                            <div class="status-number vote-vastu"><?php echo $stats['vastu_haali']; ?></div>
                            <div class="status-label">Vastu</div>
                        </div>
                        <div class="status-item">
                            <div class="status-number"><?php echo 11 - $stats['haaletanute_arv']; ?></div>
                            <div class="status-label">Puudu</div>
                        </div>
                    </div>
                </div>
                
                <!-- Hääletamise vorm -->
                <div class="voting-form">
                    <h2>🗳️ Anna oma hääl</h2>
                    <?php if ($isExpired): ?>
                        <p class="warning-text">⚠️ Hääletus on ametlikult lõppenud, kuid saad endiselt hääletada (ei mõjuta tulemust).</p>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="eesnimi">Eesnimi:</label>
                            <input type="text" id="eesnimi" name="eesnimi" required 
                                   value="<?php echo htmlspecialchars($_POST['eesnimi'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="perenimi">Perenimi:</label>
                            <input type="text" id="perenimi" name="perenimi" required 
                                   value="<?php echo htmlspecialchars($_POST['perenimi'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Vali oma seisukoht:</label>
                            <div class="radio-group">
                                <label class="radio-option" for="poolt">
                                    <input type="radio" id="poolt" name="otsus" value="poolt" required>
                                    <span>👍 Poolt</span>
                                </label>
                                <label class="radio-option" for="vastu">
                                    <input type="radio" id="vastu" name="otsus" value="vastu" required>
                                    <span>👎 Vastu</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary <?php echo $isExpired ? 'btn-warning' : ''; ?>">
                            <?php echo $isExpired ? '⚠️ Anna hääl (ei mõjuta tulemust)' : '🗳️ Anna hääl'; ?>
                        </button>
                    </form>
                </div>
                
                <!-- Hääletuse ajalugu -->
                <?php if (!empty($votingHistory)): ?>
                <div class="voting-history">
                    <h3>📋 Hääletanud isikud</h3>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Nimi</th>
                                <th>Hääl</th>
                                <th>Aeg</th>
                                <th>Staatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($votingHistory as $vote): ?>
                            <?php
                                // Kontrolli, kas see hääl anti enne 5 minuti möödumist
                                $voteTime = new DateTime($vote['haaletuse_aeg']);
                                $startTime = new DateTime($stats['h_alguse_aeg']);
                                $voteElapsed = $voteTime->getTimestamp() - $startTime->getTimestamp();
                                $isOfficialVote = $voteElapsed <= 300; // 5 minutit = 300 sekundit
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vote['eesnimi'] . ' ' . $vote['perenimi']); ?></td>
                                <td class="vote-<?php echo $vote['otsus']; ?>">
                                    <?php echo $vote['otsus'] === 'poolt' ? '👍 Poolt' : '👎 Vastu'; ?>
                                </td>
                                <td><?php echo date('H:i:s', strtotime($vote['haaletuse_aeg'])); ?></td>
                                <td>
                                    <?php if ($isOfficialVote): ?>
                                        <span class="status-official">✅ Ametlik</span>
                                    <?php else: ?>
                                        <span class="status-unofficial">⚠️ Mitteametlik</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-warning">
                    <h2>🚫 Aktiivset hääletust ei leitud</h2>
                    <p>Hetkel ei ole ühtegi aktiivset hääletust.</p>
                    <div>
                        <a href="start_voting.php" class="btn-secondary">⚙️ Mine haldusvaatesse</a>
                    </div>
                </div>
                
                <!-- Kiire hääletuse algatamine -->
                <div class="voting-form">
                    <h2>🚀 Või alusta kohe uut hääletust</h2>
                    <form method="POST" action="start_voting.php">
                        <input type="hidden" name="start_voting" value="1">
                        <button type="submit" class="btn-primary" 
                                onclick="return confirm('Kas soovite algatada uue 5-minutilise hääletuse?')">
                            🚀 Algata uus hääletus
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Radio button'ide visuaalne uuendamine
        document.querySelectorAll('input[name="otsus"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.radio-option').forEach(option => {
                    option.classList.remove('selected');
                });
                this.closest('.radio-option').classList.add('selected');
            });
        });

        // PARANDATUD taimer
        <?php if ($activeVoting && !$isExpired && $timeLeft > 0): ?>
        let timeLeft = <?php echo $timeLeft; ?>;
        const countdown = document.getElementById('countdown');
        const timer = document.getElementById('timer');
        const submitBtn = document.querySelector('button[type="submit"]');

        console.log('=== TAIMER ALGUS ===');
        console.log('Hääletus ID:', <?php echo $activeVoting['tulemuse_id']; ?>);
        console.log('Aega jäänud (PHP):', timeLeft, 'sekundit');

        // Kontrollime, et aeg on mõistlik (0-300 sekundit)
        if (timeLeft <= 0 || timeLeft > 300) {
            console.log('PROBLEEM: Vigane aeg, näitame lõppenud. Aeg:', timeLeft);
            timer.classList.add('expired');
            timer.innerHTML = '⏰ Ametlik hääletus on lõppenud! (Saad edasi hääletada, kuid see ei mõjuta tulemust)';
            if (submitBtn) {
                submitBtn.classList.add('btn-warning');
                submitBtn.textContent = '⚠️ Anna hääl (ei mõjuta tulemust)';
            }
        } else {
            console.log('OK: Käivitame taimer. Aeg:', timeLeft, 'sekundit');
            
            const updateTimer = () => {
                if (timeLeft <= 0) {
                    console.log('TAIMER: Aeg sai otsa');
                    timer.classList.add('expired');
                    timer.innerHTML = '⏰ Ametlik hääletus on lõppenud! (Saad edasi hääletada, kuid see ei mõjuta tulemust)';
                    if (submitBtn) {
                        submitBtn.classList.add('btn-warning');
                        submitBtn.textContent = '⚠️ Anna hääl (ei mõjuta tulemust)';
                    }
                    clearInterval(timerInterval);
                    // Uuenda lehte 3 sekundi pärast
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (countdown) {
                    countdown.textContent = timeString;
                }
                
                timeLeft--;
            };

            // Uuenda kohe esimest korda
            updateTimer();
            // Seejärel iga sekund
            const timerInterval = setInterval(updateTimer, 1000);
        }
        <?php else: ?>
        console.log('=== TAIMER SEISATUD ===');
        console.log('Hääletus ei ole aktiivne või on lõppenud');
        console.log('activeVoting:', <?php echo $activeVoting ? 'true' : 'false'; ?>);
        console.log('isExpired:', <?php echo $isExpired ? 'true' : 'false'; ?>);
        console.log('timeLeft:', <?php echo $timeLeft; ?>);
        <?php endif; ?>

        // Automaatne uuendamine iga 30 sekundi järel
        setInterval(() => {
            console.log('Lehte uuendatakse...');
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>