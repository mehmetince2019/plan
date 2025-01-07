<?php
$host = "localhost";
$user = "demo_user";
$password = "demo_pass!";
$dbname = "demo_plan";

// Datenbankverbindung
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Datenbankverbindung fehlgeschlagen: " . $conn->connect_error);
}

// Datumskontrolle
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Aufgaben abrufen
$sql = "SELECT * FROM tasks WHERE date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// Wenn keine Einträge vorhanden sind, Standardfächer hinzufügen
if (empty($tasks)) {
    $defaultSubjects = [
        'ing1- Effortless', 'ing2- Wiederholung', 'ing3- anki',
        'de- Zeitung', 'de- Buch', 'de- Wiederholung',
        'de- anki', 'ccna', 'it', 'Sport', 'Fastint'
    ];
    $insertSql = "INSERT INTO tasks (date, subject, is_completed, status) VALUES (?, ?, 0, 'ausstehend')";
    $insertStmt = $conn->prepare($insertSql);
    foreach ($defaultSubjects as $subject) {
        $insertStmt->bind_param("ss", $date, $subject);
        $insertStmt->execute();
    }
    $insertStmt->close();
    header("Location: ?date=$date");
    exit();
}

// Status zwischen "+" und "-" ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggleId = intval($_POST['toggle_id']);
    
    // Status ändern
    $toggleSql = "UPDATE tasks SET is_completed = NOT is_completed, status = IF(is_completed, 'abgeschlossen', 'ausstehend') WHERE id = ?";
    $toggleStmt = $conn->prepare($toggleSql);
    $toggleStmt->bind_param("i", $toggleId);
    $toggleStmt->execute();
    $toggleStmt->close();
    
    header("Location: ?date=$date");
    exit();
}

// Neuen Eintrag hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_subject'])) {
    $newSubject = htmlspecialchars($_POST['new_subject']);
    $insertSql = "INSERT INTO tasks (date, subject, is_completed, status) VALUES (?, ?, 0, 'ausstehend')";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("ss", $date, $newSubject);
    $insertStmt->execute();
    $insertStmt->close();
    
    header("Location: ?date=$date");
    exit();
}

// Eintrag entfernen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_subject'])) {
    $removeSubject = htmlspecialchars($_POST['remove_subject']);
    $deleteSql = "DELETE FROM tasks WHERE date = ? AND subject = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("ss", $date, $removeSubject);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    header("Location: ?date=$date");
    exit();
}

// JSON-Daten für den Kalender erstellen
$calendarSql = "SELECT date, COUNT(*) AS task_count, 
                SUM(CASE WHEN status = 'abgeschlossen' THEN 1 ELSE 0 END) AS completed_count 
                FROM tasks GROUP BY date";
$calendarResult = $conn->query($calendarSql);
$events = [];
while ($row = $calendarResult->fetch_assoc()) {
    $events[] = [
        'title' => "{$row['completed_count']} / {$row['task_count']} abgeschlossen",
        'start' => $row['date'],
        'color' => $row['completed_count'] == $row['task_count'] ? 'green' : 'Secondary'
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studienplan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css">
    <style>
        .btn-toggle {
            width: 150px; /* Breite des Buttons einstellen */
            font-size: 16px; /* Schriftgröße einstellen */
        }
    </style>
</head>
<body>
<div class="container mt-2">
    <!-- <h1 class="mb-4">Studienplan</h1> -->

    <!-- Kalender -->
    <div id="calendar" class="mb-1"></div>

    <h3 class="mb-3"> <?php echo htmlspecialchars($date); ?></h3>
    <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>" class="btn btn-primary"><< Vorheriger Tag</a>
    <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>" class="btn btn-primary">Nächster Tag >></a>

    <!-- Neuen Eintrag hinzufügen -->
    <form method="POST" class="mt-3">
        <div class="input-group">
            <input type="text" name="new_subject" class="form-control" placeholder="Neues Fach hinzufügen" required>
            <button type="submit" class="btn btn-success"> Ekle </button>
        </div>
    </form>

    <!-- Mevcut Dersleri Listele ve Çıkart -->
    <form method="POST" class="mt-3">
        <div class="input-group">
            <select name="remove_subject" class="form-select" required>
                <option value=""> Seç </option>
                <?php foreach ($tasks as $task): ?>
                    <option value="<?php echo htmlspecialchars($task['subject']); ?>"><?php echo htmlspecialchars($task['subject']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-danger">Çıkart</button>
        </div>
    </form>

    <table class="table table-bordered mt-4">
        <thead>
        <!--<tr>
            <th>Fach</th>
            <th>+/-</th>
            <th>Ziel</th>
            <th>Fortschritt</th>
            <th>Notizen</th>
            <th>Status</th>
            <th>Aktualisieren</th>
        </tr>-->
        </thead>
        <tbody>
        <?php foreach ($tasks as $task): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                    <td><?php echo htmlspecialchars($task['subject']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_id" value="<?php echo $task['id']; ?>">
                            <button type="submit" class="btn btn-<?php echo $task['is_completed'] ? 'success' : 'Warning'; ?> btn-toggle">
                                <?php echo $task['is_completed'] ? '✔ ' : ' - '; ?>
                            </button>
                        </form>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: <?php echo json_encode($events); ?>,
            dateClick: function (info) {
                window.location.href = `?date=${info.dateStr}`;
            }
        });
        calendar.render();
    });
</script>
    </body>
</html>
