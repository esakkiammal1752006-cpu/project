<?php
// Start session and database connection
session_start();

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

// Get statistics
$total_reports = 0;
$completed_reports = 0;
$citizens_count = 0;
$workers_count = 0;

// Get counts from database
$result = $conn->query("SELECT COUNT(*) as total FROM reports");
if($result) $total_reports = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='completed'");
if($result) $completed_reports = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='citizen'");
if($result) $citizens_count = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='worker'");
if($result) $workers_count = $result->fetch_assoc()['total'];

// Get recent issues
$recent_issues = $conn->query("SELECT * FROM reports ORDER BY created_at DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Home</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS - Green theme matching worker_dashboard.php -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            overflow-x: hidden;
            background: #f4f6f9;
        }
        
        /* Background Image with Overlay */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: url('https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') no-repeat center center fixed;
            background-size: cover;
        }
        
        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.85), rgba(40, 167, 69, 0.85));
            z-index: -1;
        }
        
        /* Content Wrapper */
        .content-wrapper {
            position: relative;
            z-index: 1;
            background: transparent;
            min-height: 100vh;
        }
        
        /* Navigation Bar - Green gradient matching worker_dashboard */
        .navbar {
            background: linear-gradient(135deg, #2c3e50, #28a745) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            padding: 15px 0;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            padding: 5px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .navbar-brand i {
            font-size: 2rem;
            margin-right: 10px;
            color: #ffd700;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 500;
            padding: 8px 20px;
            margin: 0 5px;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
            font-weight: 600;
        }
        
        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        /* Dropdown Menu */
        .dropdown-menu {
            background: white;
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 10px;
        }
        
        .dropdown-item {
            color: #2c3e50 !important;
            border-radius: 5px;
            padding: 8px 15px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white !important;
            transform: translateX(5px);
        }
        
        /* Hero Section - Green gradient */
        .hero-section {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.9), rgba(40, 167, 69, 0.9));
            color: white;
            padding: 120px 0 100px;
            margin-top: 76px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .hero-section h1 {
            font-weight: 700;
            font-size: 3.2rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .hero-section .lead {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 30px;
        }
        
        .hero-section .btn-light {
            background: white;
            color: #28a745;
            border: 2px solid white;
            font-weight: 600;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .hero-section .btn-light:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .hero-section .btn-outline-light {
            border: 2px solid white;
            color: white;
            font-weight: 600;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .hero-section .btn-outline-light:hover {
            background: white;
            color: #28a745;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .hero-section .rounded-circle {
            width: 200px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 5px solid rgba(255,255,255,0.3);
        }
        
        .hero-section .rounded-circle i {
            font-size: 80px;
            color: #28a745;
        }
        
        /* Statistics Section */
        .stats-section {
            padding: 60px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            border-left: 6px solid;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card:nth-child(1) { border-left-color: #2c3e50; }
        .stat-card:nth-child(2) { border-left-color: #28a745; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #3498db; }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: #2c3e50; color: white; }
        .stat-card:nth-child(2) .stat-icon { background: #28a745; color: white; }
        .stat-card:nth-child(3) .stat-icon { background: #f39c12; color: white; }
        .stat-card:nth-child(4) .stat-icon { background: #3498db; color: white; }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 16px;
            font-weight: 600;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Section Titles */
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            position: relative;
            display: inline-block;
            padding-bottom: 15px;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #2c3e50, #28a745);
            border-radius: 2px;
        }
        
        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
            border: none;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #2c3e50, #28a745);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 40px;
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .feature-card h4 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .feature-card p {
            color: #7f8c8d;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* Issue Cards */
        .issue-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
            border: none;
            border-left: 6px solid #28a745;
        }
        
        .issue-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 40px rgba(0,0,0,0.15);
        }
        
        .issue-card .badge {
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        /* Call to Action */
        .cta-section {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: bottom;
            opacity: 0.2;
        }
        
        .cta-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-section .lead {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 30px;
        }
        
        .cta-section .btn-success {
            background: white;
            color: #28a745;
            border: 2px solid white;
            padding: 12px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .cta-section .btn-success:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .cta-section .btn-outline-success {
            border: 2px solid white;
            color: white;
            padding: 12px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .cta-section .btn-outline-success:hover {
            background: white;
            color: #28a745;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        /* Footer */
        footer {
            background: #1e2b3a;
            color: white;
            padding: 60px 0 30px;
            position: relative;
        }
        
        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #2c3e50, #28a745, #f39c12, #3498db);
            background-size: 300% 100%;
            animation: gradientMove 5s linear infinite;
        }
        
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 300% 50%; }
        }
        
        footer h4, footer h5 {
            font-weight: 600;
            margin-bottom: 25px;
        }
        
        footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        footer a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .social-icons a {
            display: inline-block;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            transform: translateY(-5px);
        }
        
        /* Buttons */
        .btn-shine {
            position: relative;
            overflow: hidden;
        }
        
        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            background: rgba(255,255,255,0.3);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        
        .btn-shine:hover::after {
            left: 140%;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.2rem;
            }
            
            .hero-section .lead {
                font-size: 1.1rem;
            }
            
            .hero-section .rounded-circle {
                width: 150px;
                height: 150px;
                margin-top: 30px;
            }
            
            .hero-section .rounded-circle i {
                font-size: 60px;
            }
            
            .stat-number {
                font-size: 32px;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .cta-section h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Image with Green Overlay -->
    <div class="background-container"></div>
    <div class="background-overlay"></div>
    
    <div class="content-wrapper">
        <!-- Navigation Bar - Green gradient matching worker_dashboard -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-trash-alt"></i> Swachh Bharat
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                        
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($_SESSION['user_type'] == 'citizen'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                            <?php elseif($_SESSION['user_type'] == 'worker'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="worker_dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="admin.php">
                                    <i class="fas fa-user-shield"></i> Admin
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="display-4 fw-bold">Welcome to Swachh Bharat</h1>
                        <p class="lead">Join hands to keep our city clean, green, and beautiful. Report issues, track progress, and earn rewards for your contributions.</p>
                        
                        <div class="mt-4">
                            <?php if(!isset($_SESSION['user_id'])): ?>
                                <a href="register.php" class="btn btn-light btn-lg me-3 btn-shine">
                                    <i class="fas fa-user-plus"></i> Join Now
                                </a>
                                <a href="login.php" class="btn btn-outline-light btn-lg btn-shine">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            <?php else: ?>
                                <a href="report.php" class="btn btn-light btn-lg me-3 btn-shine">
                                    <i class="fas fa-plus-circle"></i> Report Issue
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-light btn-lg btn-shine">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-4 text-center">
                        <div class="bg-white rounded-circle p-4 d-inline-block">
                            <i class="fas fa-trash-alt fa-6x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistics Section -->
        <section class="stats-section">
            <div class="container">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $total_reports; ?></div>
                            <div class="stat-label">Issues Reported</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $completed_reports; ?></div>
                            <div class="stat-label">Issues Solved</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $citizens_count; ?></div>
                            <div class="stat-label">Active Citizens</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-number"><?php echo $workers_count; ?></div>
                            <div class="stat-label">Municipal Workers</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="container mb-5">
            <div class="section-title">
                <h2>How It Works</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4>1. Report Issue</h4>
                        <p>Take a photo and report garbage overflow, road damage, street light issues, or other civic problems.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>2. Admin Review</h4>
                        <p>Municipality administrators review your report and assign it to the appropriate department.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h4>3. Work Execution</h4>
                        <p>Municipal workers complete the task and update the status in real-time.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h4>4. Earn Rewards</h4>
                        <p>Get points for each valid report. Redeem points for rewards and certificates.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent Issues -->
        <section class="container mb-5">
            <div class="section-title">
                <h2>Recent Issues Reported</h2>
            </div>
            
            <?php if($recent_issues && $recent_issues->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while($issue = $recent_issues->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="issue-card">
                                <h5 class="card-title mb-3">
                                    <?php echo htmlspecialchars($issue['title']); ?>
                                </h5>
                                
                                <div class="mb-3">
                                    <span class="badge bg-secondary me-2">
                                        <?php echo ucfirst($issue['category']); ?>
                                    </span>
                                    
                                    <span class="status-badge 
                                        <?php 
                                        if($issue['status'] == 'pending') echo 'status-pending';
                                        elseif($issue['status'] == 'in_progress') echo 'status-progress';
                                        else echo 'status-completed';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                    </span>
                                </div>
                                
                                <p class="card-text text-muted mb-3">
                                    <?php echo substr(htmlspecialchars($issue['description']), 0, 100); ?>...
                                </p>
                                
                                <div class="text-muted small">
                                    <i class="fas fa-map-marker-alt text-success me-1"></i>
                                    <?php echo htmlspecialchars($issue['location']); ?>
                                </div>
                                
                                <div class="text-muted small mt-1">
                                    <i class="far fa-clock text-success me-1"></i>
                                    <?php echo date('d M Y', strtotime($issue['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="text-center mt-5">
                    <a href="issues.php" class="btn btn-success btn-lg btn-shine px-5">
                        <i class="fas fa-list me-2"></i> View All Issues
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-4" style="background: white; border-left: 6px solid #28a745; border-radius: 10px;">
                    <i class="fas fa-info-circle fa-2x text-success mb-3"></i>
                    <h5 class="mb-0">No issues reported yet. Be the first to report!</h5>
                </div>
            <?php endif; ?>
        </section>

        <!-- Call to Action -->
        <section class="cta-section">
            <div class="container text-center">
                <h2 class="mb-4">Ready to Make a Difference?</h2>
                <p class="lead mb-4">Join thousands of citizens who are actively contributing to a cleaner city.</p>
                
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-success btn-lg me-3 btn-shine">
                        <i class="fas fa-user-plus"></i> Sign Up Now
                    </a>
                    <a href="login.php" class="btn btn-outline-success btn-lg btn-shine">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php else: ?>
                    <a href="report.php" class="btn btn-success btn-lg btn-shine">
                        <i class="fas fa-plus-circle"></i> Report New Issue
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <h4 class="mb-4">
                            <i class="fas fa-trash-alt text-success me-2"></i> Swachh Bharat
                        </h4>
                        <p class="text-white-50">A citizen-centric platform for reporting and resolving municipal issues efficiently.</p>
                        <div class="social-icons mt-4">
                            <a href="#" class="text-white me-3">
                                <i class="fab fa-facebook fa-2x"></i>
                            </a>
                            <a href="#" class="text-white me-3">
                                <i class="fab fa-twitter fa-2x"></i>
                            </a>
                            <a href="#" class="text-white">
                                <i class="fab fa-instagram fa-2x"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <h5 class="mb-4">Quick Links</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <a href="index.php" class="text-white-50 text-decoration-none">
                                    <i class="fas fa-home me-2 text-success"></i> Home
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="report.php" class="text-white-50 text-decoration-none">
                                    <i class="fas fa-plus-circle me-2 text-success"></i> Report Issue
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="login.php" class="text-white-50 text-decoration-none">
                                    <i class="fas fa-sign-in-alt me-2 text-success"></i> Login
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="register.php" class="text-white-50 text-decoration-none">
                                    <i class="fas fa-user-plus me-2 text-success"></i> Register
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <h5 class="mb-4">Contact Us</h5>
                        <p class="text-white-50 mb-2">
                            <i class="fas fa-phone me-2 text-success"></i> 1800-123-4567
                        </p>
                        <p class="text-white-50 mb-2">
                            <i class="fas fa-envelope me-2 text-success"></i> help@swachhbharat.gov
                        </p>
                        <p class="text-white-50 mb-2">
                            <i class="fas fa-map-marker-alt me-2 text-success"></i> Municipal Corporation, City Center
                        </p>
                        <p class="text-white-50">
                            <i class="far fa-clock me-2 text-success"></i> Mon-Fri: 9AM-6PM
                        </p>
                    </div>
                </div>
                
                <hr class="bg-light opacity-25 my-4">
                
                <div class="row">
                    <div class="col-md-12 text-center">
                        <p class="mb-0 text-white-50">
                            &copy; <?php echo date('Y'); ?> Swachh Bharat Citizen Portal. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to feature cards
            const featureCards = document.querySelectorAll('.feature-card, .stat-card, .issue-card');
            featureCards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.5s ease forwards ${index * 0.1}s`;
            });
            
            // Update year in footer
            const yearElement = document.querySelector('footer p.mb-0');
            if (yearElement) {
                const currentYear = new Date().getFullYear();
                yearElement.innerHTML = `&copy; ${currentYear} Swachh Bharat Citizen Portal. All rights reserved.`;
            }
            
            // Add fadeInUp animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>