<?php
require_once "core.php";
require_once "google_oauth.php";

$msg = "";
$activeTab = "login";

/* ================= REGISTER ================= */
if (isset($_POST['register'])) {

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    $activeTab = "register";

    if ($username === "" || $email === "" || $password === "" || $confirm === "") {
        $msg = "All fields are required";
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters";
    } else {

        // Check duplicate username/email
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE username=? OR email=?"
        );
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $msg = "Username or Email already exists";
        } else {

            $hash = password_hash($password, PASSWORD_BCRYPT);

            // Insert user
            $stmt = $conn->prepare(
                "INSERT INTO users (username, email)
                 VALUES (?, ?)"
            );
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $uid = $stmt->insert_id;
            $stmt->close();

            // Insert auth
            $stmt = $conn->prepare(
                "INSERT INTO auth (uid, username, password)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iss", $uid, $username, $hash);
            $stmt->execute();
            $stmt->close();

            // Store UID for Google OAuth (one-time)
            $_SESSION['pending_google_uid'] = $uid;

            // Redirect to Google OAuth
            header("Location: " . googleAuthUrl());
            exit;
        }
    }
}

/* ================= LOGIN ================= */
if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        "SELECT auth.uid, auth.password
         FROM auth
         JOIN users ON users.id = auth.uid
         WHERE auth.username=? AND users.blocked=0"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['uid'] = $user['uid'];
        logUserLogin("login");

        header("Location: dashboard.php");
        exit;

    } else {
        $msg = "Invalid username or password";
        $activeTab = "login";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SecureDrive – Login</title>

<style>
:root{
    --purple:#7c3aed;
    --purple-dark:#5b21b6;
    --bg:#f5f3ff;
    --card:#ffffffcc;
    --text:#1f2937;
}

*{
    box-sizing:border-box;
    font-family:Inter,'Segoe UI',sans-serif;
}

body{
    margin:0;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:
        radial-gradient(circle at top left,#ede9fe,#fdf4ff),
        linear-gradient(120deg,#ede9fe,#f5f3ff);
}

.card{
    width:420px;
    background:var(--card);
    backdrop-filter:blur(14px);
    border-radius:18px;
    box-shadow:0 25px 60px rgba(0,0,0,.15);
    overflow:hidden;
    animation:slideUp .6s ease;
}

@keyframes slideUp{
    from{opacity:0;transform:translateY(30px);}
    to{opacity:1;transform:none;}
}

.header{
    background:linear-gradient(135deg,var(--purple),var(--purple-dark));
    padding:28px;
    color:#fff;
    text-align:center;
}

.tabs{
    display:flex;
    background:#f1f5f9;
}

.tabs button{
    flex:1;
    padding:14px;
    border:none;
    background:none;
    font-weight:600;
    color:#6b7280;
    cursor:pointer;
}

.tabs button.active{
    background:#fff;
    color:var(--purple);
    border-bottom:3px solid var(--purple);
}

.form{
    padding:26px;
}

.field{
    position:relative;
    margin-bottom:18px;
}

.field input{
    width:100%;
    padding:14px 12px;
    border-radius:10px;
    border:1px solid #d1d5db;
    background:transparent;
    outline:none;
}

.field label{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    background:#fff;
    padding:0 6px;
    font-size:13px;
    color:#6b7280;
    pointer-events:none;
    transition:.3s;
}

.field input:focus + label,
.field input:not(:placeholder-shown) + label{
    top:-6px;
    font-size:12px;
    color:var(--purple);
}

button.submit{
    width:100%;
    padding:14px;
    background:linear-gradient(135deg,var(--purple),var(--purple-dark));
    color:#fff;
    border:none;
    border-radius:12px;
    font-weight:600;
    cursor:pointer;
}

.msg{
    background:#fee2e2;
    color:#991b1b;
    padding:12px;
    border-radius:10px;
    margin-bottom:18px;
    text-align:center;
}
</style>

<script>
function switchTab(tab){
    document.getElementById("loginForm").style.display =
        tab==="login" ? "block":"none";
    document.getElementById("registerForm").style.display =
        tab==="register" ? "block":"none";

    document.getElementById("loginTab").classList.toggle("active",tab==="login");
    document.getElementById("registerTab").classList.toggle("active",tab==="register");
}
</script>
</head>

<body>

<div class="card">

<div class="header">
    <h1>SecureDrive</h1>
    <p>Encrypted Cloud Storage</p>
</div>

<div class="tabs">
    <button id="loginTab"
            class="<?= $activeTab==='login'?'active':'' ?>"
            onclick="switchTab('login')">Login</button>

    <button id="registerTab"
            class="<?= $activeTab==='register'?'active':'' ?>"
            onclick="switchTab('register')">Register</button>
</div>

<div class="form">

<?php if ($msg): ?>
<div class="msg"><?= e($msg) ?></div>
<?php endif; ?>

<!-- LOGIN -->
<form method="post" id="loginForm"
      style="<?= $activeTab==='login'?'':'display:none' ?>">

    <div class="field">
        <input name="username" required placeholder=" ">
        <label>Username</label>
    </div>

    <div class="field">
        <input type="password" name="password" required placeholder=" ">
        <label>Password</label>
    </div>

    <button class="submit" name="login">Login</button>
</form>

<!-- REGISTER -->
<form method="post" id="registerForm"
      style="<?= $activeTab==='register'?'':'display:none' ?>">

    <div class="field">
        <input name="username" required placeholder=" ">
        <label>Username</label>
    </div>

    <div class="field">
        <input type="email" name="email" required placeholder=" ">
        <label>Email</label>
    </div>

    <div class="field">
        <input type="password" name="password" required placeholder=" ">
        <label>Password</label>
    </div>

    <div class="field">
        <input type="password" name="confirm_password" required placeholder=" ">
        <label>Confirm Password</label>
    </div>

    <button class="submit" name="register">Create Account</button>
</form>

</div>
</div>

<script>
switchTab("<?= $activeTab ?>");
</script>

</body>
</html>
