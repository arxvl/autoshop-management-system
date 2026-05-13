<?php
// includes/header.php
// Set a default title if one isn't provided by the parent page
$pageTitle = isset($pageTitle) ? $pageTitle : "Patrick Auto Repair System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Base inline styles to ensure immediate structure before you build style.css */
        :root {
            --primary-bg: #1f2937;
            --secondary-bg: #f3f4f6;
            --text-main: #111827;
            --text-light: #6b7280;
            --accent: #3b82f6;
            --danger: #ef4444;
            --success: #10b981;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: var(--secondary-bg); color: var(--text-main); display: flex; flex-direction: column; min-height: 100vh; }
        .wrapper { display: flex; flex: 1; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }

        /* --- Modal Popup Styles --- */
        .modal-overlay { 
            display: none; /* Keeps it hidden until clicked */
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); /* Darkens the background behind the popup */
            z-index: 1000; /* Forces it to the very front of the screen */
            align-items: center; 
            justify-content: center; 
        }

        .modal-content { 
            background: #fff; 
            padding: 2rem; 
            border-radius: 8px; 
            width: 100%; 
            max-width: 550px; 
            position: relative; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
        }

        .modal-active { 
            display: flex !important; /* The Javascript triggers this to show the popup */
        }

        /* Modal formatting to make it look clean */
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
        .modal-header h2 { margin: 0; font-size: 1.25rem; }
        .btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .btn-close:hover { color: #ef4444; }
        .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem; }

        /* --- Global Form Layouts --- */
        .form-row { display: flex; gap: 1rem; width: 100%; margin-bottom: 1rem; }
        .form-row .form-group { flex: 1; margin-bottom: 0; } /* Makes boxes share the space equally */
        .form-group { margin-bottom: 1rem; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit; }
    </style>
</head>
<body>