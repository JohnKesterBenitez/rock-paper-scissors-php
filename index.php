<?php
include 'db.php';

// Processing game request via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'play_move') {
    header('Content-Type: application/json');
    
    $player_name = trim($_POST['player_name']);
    $user_choice = $_POST['choice'];
    
    $choices = ['rock' => '🪨 Rock', 'paper' => '📄 Paper', 'scissors' => '✂️ Scissors'];
    $options = ['rock', 'paper', 'scissors'];
    $computer_choice = $options[rand(0, 2)];

    // Check winner
    if ($user_choice === $computer_choice) {
        $outcome = 'draw';
        $message = "Draw! Pareho kayong pumili ng " . $choices[$user_choice];
    } elseif (
        ($user_choice === 'rock' && $computer_choice === 'scissors') ||
        ($user_choice === 'paper' && $computer_choice === 'rock') ||
        ($user_choice === 'scissors' && $computer_choice === 'paper')
    ) {
        $outcome = 'win';
        $message = "Panalo ka! Ang " . $choices[$user_choice] . " mo ay tinalo ang " . $choices[$computer_choice];
    } else {
        $outcome = 'loss';
        $message = "Talo ka! Tinalo ng " . $choices[$computer_choice] . " ang " . $choices[$user_choice] . " mo";
    }

    // Save/Update in MySQL Database (my_app_db)
    if (!empty($player_name)) {
        $stmt = $conn->prepare("SELECT id FROM rps_players WHERE player_name = ?");
        $stmt->bind_param("s", $player_name);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            if ($outcome === 'win') {
                $update = $conn->prepare("UPDATE rps_players SET wins = wins + 1 WHERE player_name = ?");
            } elseif ($outcome === 'loss') {
                $update = $conn->prepare("UPDATE rps_players SET losses = losses + 1 WHERE player_name = ?");
            } else {
                $update = $conn->prepare("UPDATE rps_players SET draws = draws + 1 WHERE player_name = ?");
            }
            $update->bind_param("s", $player_name);
            $update->execute();
        } else {
            $wins = ($outcome === 'win') ? 1 : 0;
            $losses = ($outcome === 'loss') ? 1 : 0;
            $draws = ($outcome === 'draw') ? 1 : 0;

            $insert = $conn->prepare("INSERT INTO rps_players (player_name, wins, losses, draws) VALUES (?, ?, ?, ?)");
            $insert->bind_param("siii", $player_name, $wins, $losses, $draws);
            $insert->execute();
        }
    }

    // Load dynamic Leaderboard
    ob_start();
    $sql = "SELECT * FROM rps_players ORDER BY wins DESC, losses ASC LIMIT 10";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$rank}</td>
                <td>" . htmlspecialchars($row['player_name']) . "</td>
                <td><span class='badge bg-success'>{$row['wins']}</span></td>
                <td><span class='badge bg-danger'>{$row['losses']}</span></td>
                <td><span class='badge bg-secondary'>{$row['draws']}</span></td>
            </tr>";
            $rank++;
        }
    } else {
        echo "<tr><td colspan='5' class='text-center text-muted'>Wala pang record sa database.</td></tr>";
    }
    $leaderboard_html = ob_get_clean();

    echo json_encode([
        'outcome' => $outcome,
        'message' => $message,
        'user_choice' => $user_choice,
        'computer_choice' => $computer_choice,
        'leaderboard' => $leaderboard_html
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Rock Paper Scissors - Student Project</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            padding-top: 30px;
        }
        .game-icon {
            font-size: 60px;
            display: inline-block;
        }
        .shake-animation {
            animation: moveHand 0.4s infinite;
        }
        @keyframes moveHand {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="container mb-5">
    <!-- Navbar / Header -->
    <div class="p-3 mb-4 bg-body-tertiary rounded-3 border text-center">
        <h2 class="fw-bold">🎮 Rock Paper Scissors Game</h2>
        <p class="text-secondary mb-0">PHP & MySQL Web Application Activity</p>
    </div>

    <div class="row g-4">
        <!-- Main Game Area -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    Play Game
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="player_name" class="form-label">Enter Player Name:</label>
                        <input type="text" id="player_name" class="form-control" placeholder="Type your name here..." required>
                    </div>

                    <div class="border rounded p-3 text-center mb-3 bg-body-secondary">
                        <div class="row align-items-center">
                            <div class="col-5">
                                <small class="text-secondary fw-bold">PLAYER</small>
                                <div id="user-icon" class="game-icon">✊</div>
                            </div>
                            <div class="col-2 fs-4 fw-bold text-danger">VS</div>
                            <div class="col-5">
                                <small class="text-secondary fw-bold">BOT</small>
                                <div id="bot-icon" class="game-icon">✊</div>
                            </div>
                        </div>
                    </div>

                    <div id="status-alert" class="alert alert-secondary text-center">
                        Pumili sa mga button sa ibaba para magsimula.
                    </div>

                    <div class="d-flex gap-2">
                        <button onclick="playMove('rock')" class="btn btn-outline-light flex-fill py-2">🪨 Rock</button>
                        <button onclick="playMove('paper')" class="btn btn-outline-light flex-fill py-2">📄 Paper</button>
                        <button onclick="playMove('scissors')" class="btn btn-outline-light flex-fill py-2">✂️ Scissors</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboard Table -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">
                    🏆 Top Player Rankings
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0 text-center align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Wins</th>
                                <th>Losses</th>
                                <th>Draws</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-table">
                            <?php
                            $sql = "SELECT * FROM rps_players ORDER BY wins DESC, losses ASC LIMIT 10";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                $rank = 1;
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$rank}</td>
                                        <td>" . htmlspecialchars($row['player_name']) . "</td>
                                        <td><span class='badge bg-success'>{$row['wins']}</span></td>
                                        <td><span class='badge bg-danger'>{$row['losses']}</span></td>
                                        <td><span class='badge bg-secondary'>{$row['draws']}</span></td>
                                    </tr>";
                                    $rank++;
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center text-muted'>Wala pang record sa database.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const moveEmojis = {
    'rock': '🪨',
    'paper': '📄',
    'scissors': '✂️'
};

function playMove(userChoice) {
    const name = document.getElementById('player_name').value.trim();
    
    if (name === "") {
        alert("Pakisulat muna ang pangalan mo bago maglaro.");
        document.getElementById('player_name').focus();
        return;
    }

    const userIcon = document.getElementById('user-icon');
    const botIcon = document.getElementById('bot-icon');
    const statusAlert = document.getElementById('status-alert');

    // Add shaking effect
    userIcon.classList.add('shake-animation');
    botIcon.classList.add('shake-animation');
    userIcon.innerText = '✊';
    botIcon.innerText = '✊';

    statusAlert.className = "alert alert-info text-center";
    statusAlert.innerText = "Bumoboto ang Bot...";

    setTimeout(() => {
        const formData = new FormData();
        formData.append('action', 'play_move');
        formData.append('player_name', name);
        formData.append('choice', userChoice);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            userIcon.classList.remove('shake-animation');
            botIcon.classList.remove('shake-animation');

            userIcon.innerText = moveEmojis[data.user_choice];
            botIcon.innerText = moveEmojis[data.computer_choice];

            if (data.outcome === 'win') {
                statusAlert.className = "alert alert-success text-center fw-bold";
            } else if (data.outcome === 'loss') {
                statusAlert.className = "alert alert-danger text-center fw-bold";
            } else {
                statusAlert.className = "alert alert-warning text-center fw-bold";
            }
            statusAlert.innerText = data.message;

            document.getElementById('leaderboard-table').innerHTML = data.leaderboard;
        })
        .catch(err => {
            console.error(err);
            userIcon.classList.remove('shake-animation');
            botIcon.classList.remove('shake-animation');
            statusAlert.className = "alert alert-danger text-center";
            statusAlert.innerText = "Error sa pag-connect sa database.";
        });
    }, 600);
}
</script>

</body>
</html>