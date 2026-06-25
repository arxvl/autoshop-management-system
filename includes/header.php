<?php
// includes/header.php
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
        :root {
            /* Modern Industrial Color Palette */
            --bg-body: #e5e7eb;          /* Steel Gray (Main Background) */
            --bg-sidebar: #111827;       /* Midnight Asphalt (Dark Sidebar) */
            --bg-sidebar-hover: #1f2937; /* Lighter Asphalt */
            
            --text-main: #1f2937;        /* Dark Charcoal Text */
            --text-light: #4b5563;       /* Muted Steel Text */
            --text-sidebar: #f3f4f6;     /* Light Gray for Sidebar */
            
            --accent: #dc2626;           /* Engine Red (Primary Action) */
            --accent-hover: #b91c1c;     /* Deep Crimson */
            
            --success: #15803d;          /* Industrial Green */
            --danger: #dc2626;           /* Engine Red */
            
            --border-color: #9ca3af;     /* Pronounced Steel Borders */
            --card-bg: #ffffff;          /* Crisp White */

            --success: #7a7a7a;
        }
        
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: var(--bg-body); color: var(--text-main); display: flex; flex-direction: column; min-height: 100vh; }
        .wrapper { display: flex; flex: 1; overflow: hidden; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }

        /* Industrial Form Elements globally */
        input, select, textarea {
            border-radius: 2px !important; /* Sharp corners */
            border: 1px solid var(--border-color) !important;
            padding: 0.6rem;
            font-family: inherit;
            background: #fff;
            color: var(--text-main);
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--text-main) !important;
            box-shadow: 0 0 0 1px var(--text-main);
        }
        
        /* Rugged Buttons */
        button, .btn {
            border-radius: 2px !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            box-shadow: 2px 2px 0px rgba(0,0,0,0.15);
            transition: transform 0.1s, box-shadow 0.1s;
        }
        button:active, .btn:active {
            transform: translate(2px, 2px);
            box-shadow: 0px 0px 0px rgba(0,0,0,0);
        }

        /* --- Modal Popup Styles --- */
        .modal-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(17, 24, 39, 0.8); /* Darker, grittier overlay */
            z-index: 1000; align-items: center; justify-content: center; 
        }
        .modal-content { 
            background: var(--card-bg); padding: 2rem; border-radius: 2px; width: 100%; max-width: 550px; position: relative; 
            box-shadow: 4px 4px 0px rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-top: 4px solid var(--accent);
        }
        .modal-active { display: flex !important; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        .modal-header h2 { margin: 0; font-size: 1.25rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-main); font-weight: 800; }
        .btn-close { background: none; border: none; box-shadow: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light); padding: 0; }
        .btn-close:hover { color: var(--danger); transform: none; box-shadow: none; }
        .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem; }

        /* --- Global Form Layouts --- */
        .form-row { display: flex; gap: 1rem; width: 100%; margin-bottom: 1rem; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 1rem; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-light); letter-spacing: 0.05em; }

        /* Global Badges (Statuses) */
        .badge { padding: 0.25rem 0.6rem; border-radius: 2px; font-size: 0.75em; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid; white-space: nowrap; }
        .status-pending { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .status-inprogress { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .status-completed, .status-paid { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .status-cancelled, .status-refunded { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .status-partial { background: #fdf4ff; color: #6d28d9; border-color: #e9d5ff; }
    </style>
</head>
<body>