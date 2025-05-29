<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        getVotingStatus();
        break;
    case 'vote':
        submitVote();
        break;
    default:
        echo json_encode(['error' => 'Tundmatu tegevus']);
        break;
}

function getVotingStatus() {
    global $pdo;
    
    try {
        $activeVoting = getActiveVoting();
        
        if (!$activeVoting) {
            echo json_encode(['error' => 'Aktiivset hääletust ei leitud']);
            return;
        }
        
        // Hangi statistika
        $stmt = $pdo->prepare("SELECT * FROM TULEMUSED WHERE tulemuse_id = ?");
        $stmt->execute([$activeVoting['tulemuse_id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Arvuta aega allesjäänud
        $start = new DateTime($stats['h_alguse_aeg']);
        $now = new DateTime();
        $elapsed = $now->getTimestamp() - $start->getTimestamp();
        $timeLeft = max(0, 300 - $elapsed);
        $isExpired = $timeLeft <= 0;
        
        // Hangi viimased hääled
        $stmt = $pdo->prepare("SELECT * FROM HAALETUS WHERE tulemuse_id = ? ORDER BY haaletuse_aeg DESC LIMIT 10");
        $stmt->execute([$activeVoting['tulemuse_id']]);
        $recentVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'timeLeft' => $timeLeft,
            'isExpired' => $isExpired,
            'recentVotes' => $recentVotes
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Andmebaasi viga: ' . $e->getMessage()]);
    }
}

function submitVote() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Vale päringumeetod']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $eesnimi = trim($input['eesnimi'] ?? '');
    $perenimi = trim($input['perenimi'] ?? '');
    $otsus = $input['otsus'] ?? '';
    
    if (empty($eesnimi) || empty($perenimi) || empty($otsus)) {
        echo json_encode(['error' => 'Kõik väljad on kohustuslikud']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("CALL AnnaHaal(?, ?, ?)");
        $stmt->execute([$eesnimi, $perenimi, $otsus]);
        
        // Kontrolli, kas hääletus on lõppenud
        $activeVoting = getActiveVoting();
        $isExpired = false;
        if ($activeVoting) {
            $isExpired = isVotingExpired($activeVoting['tulemuse_id']);
        }
        
        if ($isExpired) {
            echo json_encode([
                'success' => true, 
                'message' => 'Hääl on registreeritud, kuid ei mõjuta tulemust (hääletus on lõppenud)!',
                'expired' => true
            ]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Hääletus salvestatud edukalt!']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Viga hääletamisel: ' . $e->getMessage()]);
    }
}
?>