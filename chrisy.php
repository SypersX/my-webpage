<?php
$servername = "sql310.infinityfree.com";
$username = "if0_39698696";
$password = "1102004091085";
$dbname = "if0_39698696_chrisy_api";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$types = ['System', 'Service', 'Warning', 'API Service'];
$total = [];
$grand_total = 0;

foreach ($types as $t) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM stats WHERE type = ?");
    $stmt->bind_param("s", $t);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $total[$t] = $res['count'];
    $grand_total += $res['count'];
    $stmt->close();
}

// คำนวณเป็น %
$total_percent = [];
foreach($total as $type => $count){
    $total_percent[$type] = $grand_total ? round(($count / $grand_total) * 100, 1) : 0;
}

$status = 'offline';
$stmt = $conn->prepare("SELECT timestamp FROM stats WHERE type='bot_status' ORDER BY timestamp DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res && (time() - strtotime($res['timestamp']) < 300)) $status = 'online';
$stmt->close();
$conn->close();
?>



<?php
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
if(!isset($_SESSION['visits'])) $_SESSION['visits'] = [];
$_SESSION['visits'][] = time();

// ลบ timestamp เก่าเกิน 60 วินาที
$_SESSION['visits'] = array_filter($_SESSION['visits'], fn($t) => $t > time()-60);

// ถ้าเกิน 20 ครั้งใน 1 นาที → block
if(count($_SESSION['visits']) > 20){
    http_response_code(429);
    die('Too many requests');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chrisy Status</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<meta name="viewport" content="width=device-width, initial-scale=1.0" />

 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #1e1e2f;
    color: #eee;
    margin: 0;
    padding: 0;
}
header {
    padding: 20px;
    background: #2a2a3e;
    text-align: center;
}
h1 { margin:0; color: #fff; }
.status {
    color: <?= $status=='online' ? '#00ff00' : '#ff4d4d'; ?>;
    font-weight: bold;
}
.container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    margin: 20px;
}
.card {
    background: #2a2a3e;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    margin: 10px;
    padding: 20px;
    flex: 1 1 180px;
    max-width: 220px;
    text-align: center;
    transition: transform 0.2s;
}
.card:hover { transform: scale(1.05); }
.card h3 { margin-bottom: 10px; color: #aaa; }
.card p { font-size: 22px; font-weight: bold; color: #fff; }

canvas { max-width: 90%; margin: 40px auto; display:block; background:#2a2a3e; border-radius:12px; padding:20px; }

@media (max-width: 600px) {
    .card { flex: 1 1 80%; max-width: 90%; }
}
</style>
</head>
<body>
<header>
    <h1>Chrisy Status</h1>
</header>

<section class="container">
<?php foreach ($total_percent as $type => $percent): ?>
<div class="card">
    <h3><?= ucfirst(str_replace('_',' ', $type)) ?></h3>
    <p><?= $percent ?>%</p>
</div>
<?php endforeach; ?>
</section>

<h2 style="text-align:center;">Status BOT</h2>
<canvas id="activityChart"></canvas>

<script>
const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($total_percent)); ?>,
        datasets: [{
            label: 'Percentage',
            data: <?= json_encode(array_values($total_percent)); ?>,
            backgroundColor: [
                'rgba(75,192,192,0.7)',
                'rgba(192,75,192,0.7)',
                'rgba(192,192,75,0.7)',
                'rgba(75,75,192,0.7)'
            ],
            borderColor: [
                'rgba(75,192,192,1)',
                'rgba(192,75,192,1)',
                'rgba(192,192,75,1)',
                'rgba(75,75,192,1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero:true,
                max: 100,
                ticks: { color:'#fff', callback: function(value){return value + "%";} }
            },
            x: { ticks: { color:'#fff' } }
        },
        plugins: {
            legend: { labels: { color:'#fff' } }
        }
    }
});
</script>



<script src="https://www.google.com/recaptcha/api.js" async defer></script>


<script>
// ตัวแปรเก็บ token ของ reCAPTCHA
let recaptchaToken = '6LdKUKorAAAAACWUKkZufaM-8NKq9PWiuPlKxyNA';

// Callback ของ reCAPTCHA ต้อง global
function onRecaptchaSuccess(token){
    recaptchaToken = token;
}

// ถ้า URL มี #install
if (window.location.hash === "#install") {
    Swal.fire({
        title: 'Hello There',
        html: `
          <p>Verify You Are Human</p>
          <div class="g-recaptcha" data-sitekey="6LdKUKorAAAAACWUKkZufaM-8NKq9PWiuPlKxyNA" data-callback="onRecaptchaSuccess"></div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        preConfirm: () => {
            if(!recaptchaToken){
                Swal.showValidationMessage('กรุณากด reCAPTCHA ก่อน');
                return false;
            }
            return fetch('status/captcha.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({captcha: recaptchaToken})
            })
            .then(res => res.json())
            .then(data => {
                if(!data.success){
                    Swal.showValidationMessage('Hmm. You not pass. you are bot?');
                    return false;
                }
                return true;
            });
        }
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = "https://discord.com/oauth2/authorize?client_id=1388806995280265237&permissions=8&integration_type=0&scope=bot";
        } else {
           // window.location.href = '403.html';
        }
    });
}
</script>
<p></p>
</body>
</html>
