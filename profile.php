<?php
// Start session and database connection
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "swachh";

// Create connection
$conn = new mysqli("localhost", "root", "", "swachh", 3307);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_type = $_SESSION['user_type'];

// Initialize variables
$error = "";
$success = "";
$user_data = [];

// Fetch user data from database
$sql = "SELECT * FROM users WHERE id = '$user_id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
} else {
    $error = "User not found!";
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = $conn->real_escape_string(trim($_POST['name']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        
        // Validate input
        if (empty($name) || empty($email) || empty($phone)) {
            $error = "Name, Email, and Phone are required fields!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            // Check if email already exists (excluding current user)
            $check_email = $conn->query("SELECT id FROM users WHERE email='$email' AND id != '$user_id'");
            if ($check_email->num_rows > 0) {
                $error = "Email already registered by another user!";
            } else {
                // Update user data
                $update_sql = "UPDATE users SET 
                               name = '$name', 
                               email = '$email', 
                               phone = '$phone', 
                               address = '$address' 
                               WHERE id = '$user_id'";
                
                if ($conn->query($update_sql) === TRUE) {
                    // Update session variables
                    $_SESSION['user_name'] = $name;
                    $user_name = $name;
                    
                    // Update local user data
                    $user_data['name'] = $name;
                    $user_data['email'] = $email;
                    $user_data['phone'] = $phone;
                    $user_data['address'] = $address;
                    
                    $success = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile: " . $conn->error;
                }
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password change
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required!";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long!";
        } else {
            // Verify current password
            $check_password = $conn->query("SELECT password FROM users WHERE id = '$user_id'");
            if ($check_password->num_rows > 0) {
                $row = $check_password->fetch_assoc();
                if (password_verify($current_password, $row['password'])) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_password_sql = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";
                    
                    if ($conn->query($update_password_sql) === TRUE) {
                        $success = "Password changed successfully!";
                    } else {
                        $error = "Error changing password: " . $conn->error;
                    }
                } else {
                    $error = "Current password is incorrect!";
                }
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - My Profile</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS - Green theme matching worker_dashboard.php -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - Green gradient matching worker_dashboard */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(180deg, #2c3e50, #28a745);
            color: white;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            width: 30px;
            font-size: 18px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Bar - Matching worker_dashboard */
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.4rem;
        }
        
        .top-bar h1 i {
            color: #28a745;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info div {
            text-align: right;
        }
        
        .user-info strong {
            font-size: 15px;
            color: #1e2b3a;
            display: block;
        }
        
        .user-info .text-muted {
            font-size: 12px;
            color: #7f8c8d !important;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(45deg, #2c3e50, #28a745);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        /* Profile Container */
        .profile-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .profile-header h2 {
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .profile-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .profile-body {
            padding: 30px;
        }
        
        /* Profile Info */
        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 16px;
            padding: 25px;
            border-left: 6px solid #28a745;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #2c3e50, #28a745);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
            border: 4px solid white;
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .profile-role {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            border: 1px solid #28a745;
        }
        
        .profile-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            background: white;
            padding: 10px 15px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-left: 3px solid #28a745;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #28a745;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* Forms */
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .form-section-title i {
            color: #28a745;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s;
            background: white;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        /* Buttons */
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            color: white;
            font-size: 14px;
            width: 100%;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            background: white;
            width: 100%;
        }
        
        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        /* Messages */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        /* Info Cards */
        .info-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 3px solid #28a745;
        }
        
        .info-card:hover {
            border-color: #28a745;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.1);
            transform: translateX(5px);
            background: white;
        }
        
        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-label i {
            color: #28a745;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Password Strength */
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 2px;
        }
        
        .password-strength.weak .password-strength-bar {
            background: #dc3545;
            width: 33%;
        }
        
        .password-strength.medium .password-strength-bar {
            background: #f39c12;
            width: 66%;
        }
        
        .password-strength.strong .password-strength-bar {
            background: #28a745;
            width: 100%;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
            }
            
            .nav-link i {
                width: auto;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-stats {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .profile-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - Your original HTML structure unchanged -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-trash-alt"></i> Swachh Bharat</h3>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>
                <span>Report Issue</span>
            </a>
            <a href="my_reports.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>My Reports</span>
            </a>
            <a href="rewards.php" class="nav-link">
                <i class="fas fa-award"></i>
                <span>Rewards</span>
            </a>
            <a href="profile.php" class="nav-link active">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content - Your original HTML structure unchanged -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="mb-0"><i class="fas fa-user-circle"></i> My Profile</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                    <div class="text-muted" style="font-size: 12px;"><?php echo ucfirst($user_type); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Profile Container -->
        <div class="profile-container">
            <div class="profile-header">
                <h2 class="mb-3"><i class="fas fa-user-cog"></i> Account Settings</h2>
                <p class="mb-0">Manage your profile information and security settings</p>
            </div>
            
            <div class="profile-body">
                <!-- Profile Info Section -->
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_data['name'], 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h1 class="profile-name"><?php echo htmlspecialchars($user_data['name']); ?></h1>
                        <span class="profile-role">
                            <i class="fas fa-user-tag"></i> <?php echo ucfirst($user_data['type']); ?>
                        </span>
                        <p class="mb-0" style="color: #5a6a7a;">
                            <i class="fas fa-envelope" style="color: #28a745;"></i> <?php echo htmlspecialchars($user_data['email']); ?>
                            • <i class="fas fa-phone" style="color: #28a745;"></i> <?php echo htmlspecialchars($user_data['phone']); ?>
                        </p>
                        
                        <!-- Account Statistics -->
                        <div class="profile-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo date('M Y', strtotime($user_data['created_at'])); ?></span>
                                <span class="stat-label">Member Since</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $user_data['user_id']; ?></span>
                                <span class="stat-label">User ID</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">Active</span>
                                <span class="stat-label">Status</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Left Column: Account Information -->
                    <div class="col-lg-6">
                        <!-- Update Profile Form -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <i class="fas fa-user-edit"></i> Personal Information
                            </h3>
                            
                            <form method="POST" action="" id="profileForm">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" 
                                               value="<?php echo htmlspecialchars($user_data['name']); ?>" 
                                               placeholder="Enter your full name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                               placeholder="Enter your email" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($user_data['phone']); ?>" 
                                               placeholder="Enter your phone number" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">User Type</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo ucfirst($user_data['type']); ?>" 
                                               readonly style="background: #f8f9fa;">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3" 
                                              placeholder="Enter your address"><?php echo htmlspecialchars($user_data['address']); ?></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Right Column: Security Settings -->
                    <div class="col-lg-6">
                        <!-- Change Password Form -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <i class="fas fa-lock"></i> Security Settings
                            </h3>
                            
                            <form method="POST" action="" id="passwordForm">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" 
                                           placeholder="Enter current password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" 
                                           id="newPassword" placeholder="Enter new password" required
                                           onkeyup="checkPasswordStrength()">
                                    <div class="password-strength" id="passwordStrength">
                                        <div class="password-strength-bar"></div>
                                    </div>
                                    <small class="text-muted">Password must be at least 6 characters long</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           id="confirmPassword" placeholder="Confirm new password" required>
                                    <div id="passwordMatch" class="mt-2"></div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <i class="fas fa-info-circle"></i> Account Information
                            </h3>
                            
                            <div class="info-card">
                                <div class="info-label">
                                    <i class="fas fa-id-card"></i> User ID
                                </div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($user_data['user_id']); ?>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">
                                    <i class="fas fa-user-tag"></i> Account Type
                                </div>
                                <div class="info-value">
                                    <?php echo ucfirst($user_data['type']); ?>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt"></i> Member Since
                                </div>
                                <div class="info-value">
                                    <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">
                                    <i class="fas fa-history"></i> Last Updated
                                </div>
                                <div class="info-value">
                                    <?php echo !empty($user_data['updated_at']) ? date('F j, Y', strtotime($user_data['updated_at'])) : 'Never'; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <a href="logout.php" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS (Your original JavaScript unchanged) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check password match
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const passwordMatchDiv = document.getElementById('passwordMatch');
            
            function checkPasswordMatch() {
                if (newPasswordInput.value && confirmPasswordInput.value) {
                    if (newPasswordInput.value === confirmPasswordInput.value) {
                        passwordMatchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
                    } else {
                        passwordMatchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                    }
                } else {
                    passwordMatchDiv.innerHTML = '';
                }
            }
            
            newPasswordInput.addEventListener('keyup', checkPasswordMatch);
            confirmPasswordInput.addEventListener('keyup', checkPasswordMatch);
            
            // Form validation
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
                
                if (newPasswordInput.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
                
                return true;
            });
        });
        
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
                if (strength < 2) {
                    strengthBar.classList.add('weak');
                } else if (strength < 4) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
            }
        }
        
        // Confirm before logout
        document.querySelector('.btn-outline-danger').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Show success message for a few seconds
        <?php if($success): ?>
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }
            }, 5000);
        <?php endif; ?>
        
        // Show error message for a few seconds
        <?php if($error): ?>
            setTimeout(() => {
                const alert = document.querySelector('.alert-danger');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>