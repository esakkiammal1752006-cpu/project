<?php
// Start session and database connection
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in as admin
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// Database configuration
$conn = new mysqli("localhost", "root", "", "swachh", 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_username = $_SESSION['admin'];

// ✅ CREATE settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Get admin details
$admin_query = $conn->query("SELECT * FROM admin WHERE username='$admin_username'");
$admin = $admin_query->fetch_assoc();

// ✅ Handle general settings update
if (isset($_POST['update_general'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $site_email = $conn->real_escape_string($_POST['site_email']);
    $site_phone = $conn->real_escape_string($_POST['site_phone']);
    $site_address = $conn->real_escape_string($_POST['site_address']);
    
    // Update or insert settings
    $settings = [
        'site_name' => $site_name,
        'site_email' => $site_email,
        'site_phone' => $site_phone,
        'site_address' => $site_address
    ];
    
    foreach ($settings as $key => $value) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) 
                      VALUES ('$key', '$value') 
                      ON DUPLICATE KEY UPDATE setting_value = '$value'");
    }
    
    $success = "General settings updated successfully!";
}

// ✅ Handle points settings update
if (isset($_POST['update_points'])) {
    $report_points = (int)$_POST['report_points'];
    $high_priority_points = (int)$_POST['high_priority_points'];
    $completed_points = (int)$_POST['completed_points'];
    $referral_points = (int)$_POST['referral_points'];
    
    // Update or insert points settings
    $settings = [
        'report_points' => $report_points,
        'high_priority_points' => $high_priority_points,
        'completed_points' => $completed_points,
        'referral_points' => $referral_points
    ];
    
    foreach ($settings as $key => $value) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) 
                      VALUES ('$key', '$value') 
                      ON DUPLICATE KEY UPDATE setting_value = '$value'");
    }
    
    $success = "Points settings updated successfully!";
}

// ✅ Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long!";
    } else {
        // Check in admin table
        $check_admin = $conn->query("SELECT password FROM admin WHERE username='$admin_username'");
        if ($check_admin->num_rows > 0) {
            $row = $check_admin->fetch_assoc();
            if (md5($current_password) == $row['password']) {
                $new_hashed = md5($new_password);
                $conn->query("UPDATE admin SET password='$new_hashed' WHERE username='$admin_username'");
                $success = "Password changed successfully!";
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
}

// ✅ Get current settings
$settings = [];
$settings_query = $conn->query("SELECT * FROM settings");
if ($settings_query && $settings_query->num_rows > 0) {
    while($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Default values if not set
$site_name = isset($settings['site_name']) ? $settings['site_name'] : 'Swachh Bharat Citizen Portal';
$site_email = isset($settings['site_email']) ? $settings['site_email'] : 'admin@swachhbharat.gov';
$site_phone = isset($settings['site_phone']) ? $settings['site_phone'] : '1800-123-4567';
$site_address = isset($settings['site_address']) ? $settings['site_address'] : 'Municipal Corporation, City Center';
$report_points = isset($settings['report_points']) ? $settings['report_points'] : 10;
$high_priority_points = isset($settings['high_priority_points']) ? $settings['high_priority_points'] : 15;
$completed_points = isset($settings['completed_points']) ? $settings['completed_points'] : 25;
$referral_points = isset($settings['referral_points']) ? $settings['referral_points'] : 50;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Admin Settings</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #2c3e50, #28a745);
            color: white;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            font-weight: bold;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            width: 30px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(45deg, #2c3e50, #28a745);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 18px 25px;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .card-header h5 i {
            margin-right: 10px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            width: 100%;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .points-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .points-row:last-child {
            border-bottom: none;
        }
        
        .points-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .points-value input {
            width: 100px;
            text-align: center;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            color: #28a745;
            font-weight: 500;
        }
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 2px;
        }
        
        .password-strength.weak .password-strength-bar { background: #dc3545; width: 33%; }
        .password-strength.medium .password-strength-bar { background: #f39c12; width: 66%; }
        .password-strength.strong .password-strength-bar { background: #28a745; width: 100%; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h3, .sidebar-header p, .nav-link span { display: none; }
            .nav-link { justify-content: center; }
            .main-content { margin-left: 70px; }
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-trash-alt"></i> Admin</h3>
            <p>Municipal Corporation</p>
        </div>
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="admin_reports.php" class="nav-link"><i class="fas fa-list"></i><span>All Reports</span></a>
            <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i><span>Manage Users</span></a>
            <a href="admin_workers.php" class="nav-link"><i class="fas fa-tools"></i><span>Workers</span></a>
            <a href="admin_settings.php" class="nav-link active"><i class="fas fa-cog"></i><span>Settings</span></a>
            <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h4><i class="fas fa-cog"></i> System Settings</h4>
            <div>
                <strong><?php echo htmlspecialchars($admin_username); ?></strong>
                <div class="text-muted">Administrator</div>
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_username, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <!-- General Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-globe"></i> General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($site_email); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" name="site_phone" value="<?php echo htmlspecialchars($site_phone); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="site_address" rows="2"><?php echo htmlspecialchars($site_address); ?></textarea>
                        </div>
                        <button type="submit" name="update_general" class="btn-save">Save General Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- Points Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Rewards Points Settings</h5>
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <i class="fas fa-info-circle text-success"></i> Configure points earned by citizens
                    </div>
                    <form method="POST">
                        <div class="points-row">
                            <span class="points-label">Report Submission:</span>
                            <div class="points-value">
                                <input type="number" class="form-control" name="report_points" value="<?php echo $report_points; ?>" min="1" max="100" required style="width: 100px;">
                            </div>
                        </div>
                        <div class="points-row">
                            <span class="points-label">High Priority Report:</span>
                            <div class="points-value">
                                <input type="number" class="form-control" name="high_priority_points" value="<?php echo $high_priority_points; ?>" min="1" max="100" required style="width: 100px;">
                            </div>
                        </div>
                        <div class="points-row">
                            <span class="points-label">Report Completed:</span>
                            <div class="points-value">
                                <input type="number" class="form-control" name="completed_points" value="<?php echo $completed_points; ?>" min="1" max="200" required style="width: 100px;">
                            </div>
                        </div>
                        <div class="points-row mb-4">
                            <span class="points-label">Referral Bonus:</span>
                            <div class="points-value">
                                <input type="number" class="form-control" name="referral_points" value="<?php echo $referral_points; ?>" min="1" max="500" required style="width: 100px;">
                            </div>
                        </div>
                        <button type="submit" name="update_points" class="btn-save">Save Points Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- Security Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-lock"></i> Security Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" placeholder="Enter current password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" placeholder="Enter new password" required onkeyup="checkPasswordStrength()">
                            <div class="password-strength" id="passwordStrength"><div class="password-strength-bar"></div></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required>
                            <div id="passwordMatch" class="mt-2"></div>
                        </div>
                        <button type="submit" name="change_password" class="btn-save">Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- System Information Card -->
            <div class="settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> System Information</h5>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">PHP Version:</span>
                        <span class="info-value"><?php echo phpversion(); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Database:</span>
                        <span class="info-value">MySQL</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Server:</span>
                        <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Localhost'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admin Since:</span>
                        <span class="info-value"><?php echo isset($admin['created_at']) ? date('d M Y', strtotime($admin['created_at'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Reports:</span>
                        <span class="info-value"><?php echo $conn->query("SELECT COUNT(*) as total FROM reports")->fetch_assoc()['total']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Users:</span>
                        <span class="info-value"><?php echo $conn->query("SELECT COUNT(*) as total FROM users WHERE type != 'admin'")->fetch_assoc()['total']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            strengthBar.className = 'password-strength';
            if (password.length > 0) {
                if (strength < 2) strengthBar.classList.add('weak');
                else if (strength < 4) strengthBar.classList.add('medium');
                else strengthBar.classList.add('strong');
            }
        }
        
        document.getElementById('newPassword')?.addEventListener('keyup', checkPasswordMatch);
        document.getElementById('confirmPassword')?.addEventListener('keyup', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const newPass = document.getElementById('newPassword')?.value;
            const confirmPass = document.getElementById('confirmPassword')?.value;
            const matchDiv = document.getElementById('passwordMatch');
            if (newPass && confirmPass) {
                if (newPass === confirmPass) {
                    matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        }
        
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>