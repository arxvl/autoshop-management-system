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
            background: #1f2937; /* Dark background to match dashboard nav */
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-box { 
            background: #e7e6e6; 
            padding: 3rem; 
            border-radius: 10px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
            width: 100%; 
            max-width: 350px; 
        }
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
            color: #1f2937;
        }
        .brand-header i {
            font-size: 3rem;
            color: #3b82f6; /* Accent color */
            margin-bottom: 1rem;
        }
        .brand-header h2 { 
            margin: 0; 
            font-size: 1.5rem;
        }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #4b5563; font-size: 0.9em; font-weight: 500;}
        .form-group input { 
            width: 100%; 
            padding: 0.75rem 0.75rem 0.75rem 2.5rem; /* Padding for the icon */
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-group i {
            position: absolute;
            left: 0.85rem;
            top: 2.3rem;
            color: #9ca3af;
        }
        .btn { 
            width: 100%; 
            padding: 0.85rem; 
            background: #3b82f6; 
            color: #fff; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 1.05em; 
            font-weight: 600; 
            transition: background 0.2s;
        }
        .btn:hover { background: #2563eb; }
        .error { 
            background: #fee2e2; 
            color: #b91c1c; 
            padding: 0.75rem; 
            border-radius: 6px; 
            margin-bottom: 1.5rem; 
            font-size: 0.9em; 
            text-align: center; 
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="brand-header">
            <h2>System Login</h2>
        </div>

        <?php if ($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" id="username" required autocomplete="off" placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" id="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>
</html>