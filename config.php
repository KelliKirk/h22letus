<?php
// Andmebaasi ühenduse seadistused
$servername = "localhost";
$username = "vso24kirk"; // Muuda vastavalt oma cPanel kasutajanimele
$password = ";.?aI}F3Jw}W"; 
$dbname = "vso24kirk_h22letus";

// Loo ühendus
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ühenduse viga: " . $e->getMessage());
}

// Funktsioon aktiivse hääletuse leidmiseks
function getActiveVoting() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM TULEMUSED WHERE on_aktiivne = TRUE ORDER BY tulemuse_id DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Funktsioon hääletuse seisu kontrollimiseks (kas ametlik periood on lõppenud)
function isVotingExpired($tulemuse_id) {
    global $pdo;
    try {
        // Kasuta andmebaasi funktsiooni kui olemas
        $stmt = $pdo->prepare("SELECT OnHaaletusLoppenud(?) as loppenud");
        $stmt->execute([$tulemuse_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['loppenud'] == 1;
    } catch (PDOException $e) {
        // Tagavaravariant - kontrolli käsitsi andmebaasi ajaga
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    h_alguse_aeg,
                    TIMESTAMPDIFF(SECOND, h_alguse_aeg, NOW()) as elapsed_seconds
                FROM TULEMUSED 
                WHERE tulemuse_id = ?
            ");
            $stmt->execute([$tulemuse_id]);
            $voting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($voting) {
                $elapsed = $voting['elapsed_seconds'];
                return $elapsed > 300; // 5 minutit = 300 sekundit
            }
        } catch (Exception $dateEx) {
            error_log("Viga kuupäeva töötlemisel: " . $dateEx->getMessage());
        }
        return true; // Vea korral loeme lõppenuks
    }
}

// Funktsioon allesjäänud aja arvutamiseks
function getTimeLeft($tulemuse_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                h_alguse_aeg,
                TIMESTAMPDIFF(SECOND, h_alguse_aeg, NOW()) as elapsed_seconds
            FROM TULEMUSED 
            WHERE tulemuse_id = ?
        ");
        $stmt->execute([$tulemuse_id]);
        $voting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($voting) {
            $elapsed = $voting['elapsed_seconds'];
            $timeLeft = max(0, 300 - $elapsed); // 5 minutit = 300 sekundit
            return $timeLeft;
        }
    } catch (Exception $e) {
        error_log("Viga aja arvutamisel: " . $e->getMessage());
    }
    return 0; // Vea korral tagasta 0
}

// Funktsioon hääletuse andmete saamiseks koos ajaga
function getVotingWithTimeInfo($tulemuse_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM TULEMUSED WHERE tulemuse_id = ?");
        $stmt->execute([$tulemuse_id]);
        $voting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($voting) {
            $timeLeft = getTimeLeft($tulemuse_id);
            $isExpired = isVotingExpired($tulemuse_id);
            
            return [
                'voting' => $voting,
                'timeLeft' => $timeLeft,
                'isExpired' => $isExpired
            ];
        }
    } catch (Exception $e) {
        error_log("Viga hääletuse info saamisel: " . $e->getMessage());
    }
    return null;
}

// Funktsioon kontrollimaks, kas hääl anti ametliku perioodi jooksul
function isVoteOfficial($vote_time, $start_time) {
    $voteTimestamp = strtotime($vote_time);
    $startTimestamp = strtotime($start_time);
    $elapsed = $voteTimestamp - $startTimestamp;
    return $elapsed <= 300; // 5 minutit = 300 sekundit
}
?>