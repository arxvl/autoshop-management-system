<?php
// login.php
require_once 'config/auth.php';

// Redirect to dashboard if they are already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);

    global $pdo; 
    
    // Pass the credentials to our newly updated database logic
    if (login($username, $password, $pdo)) {
        redirect('dashboard.php');
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Patrick Auto Repair</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #111827; /* Midnight Asphalt */
            background-image: radial-gradient(#374151 1px, transparent 1px); /* Subtle industrial grip texture */
            background-size: 20px 20px;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-box { 
            background: #ffffff; 
            padding: 3rem; 
            border-radius: 2px; /* Sharp corners */
            border: 1px solid #9ca3af;
            border-top: 5px solid #dc2626; /* Engine Red Accent */
            box-shadow: 6px 6px 0px rgba(0,0,0,0.4); /* Blocky rugged shadow */
            width: 100%; 
            max-width: 350px; 
        }
        .brand-header {
            text-align: center;
            margin-bottom: 2.5rem;
            color: #1f2937;
        }
        .brand-header i {
            font-size: 3rem;
            color: #dc2626; /* Engine Red */
            margin-bottom: 1rem;
        }
        .brand-header h2 { 
            margin: 0; 
            font-size: 1.5rem;
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 0.05em;
        }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #4b5563; 
            font-size: 0.8em; 
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group input { 
            width: 100%; 
            padding: 0.75rem 0.75rem 0.75rem 2.5rem; /* Padding for the icon */
            border: 1px solid #9ca3af; 
            border-radius: 2px; 
            box-sizing: border-box; 
            font-size: 1rem;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1f2937;
            box-shadow: 0 0 0 1px #1f2937;
        }
        .form-group i {
            position: absolute;
            left: 0.85rem;
            top: 2.15rem;
            color: #6b7280;
        }
        .btn { 
            width: 100%; 
            padding: 0.85rem; 
            background: #dc2626; 
            color: #fff; 
            border: none; 
            border-radius: 2px; 
            cursor: pointer; 
            font-size: 0.9em; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 2px 2px 0px rgba(0,0,0,0.15);
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .btn:hover { background: #b91c1c; }
        .btn:active {
            transform: translate(2px, 2px);
            box-shadow: none;
        }
        .error { 
            background: #fef2f2; 
            color: #7f1d1d; 
            padding: 0.75rem; 
            border-radius: 2px; 
            margin-bottom: 1.5rem; 
            font-size: 0.85em; 
            text-align: center; 
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="brand-header">
            <i class="fa-solid fa-wrench"></i>
            <h2>System Login</h2>
        </div>

        <?php if ($error): ?>
            <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" id="username" required autocomplete="off" placeholder="Enter ID">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" id="password" required placeholder="Enter Passkey">
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>
</html>