<?php
// Start session and database connection
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'citizen') {
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

// Get user statistics
$total_reports = 0;
$pending_reports = 0;
$completed_reports = 0;
$in_progress_reports = 0;

// Get counts from database
$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_id = $user_id");
if($result) $total_reports = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_id = $user_id AND status='pending'");
if($result) $pending_reports = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_id = $user_id AND status='in_progress'");
if($result) $in_progress_reports = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_id = $user_id AND status='completed'");
if($result) $completed_reports = $result->fetch_assoc()['total'];

// Get recent reports
$recent_reports = $conn->query("SELECT * FROM reports WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");

// Get leaderboard data
$leaderboard = $conn->query("SELECT u.name, COUNT(r.id) as reports_count 
                             FROM users u 
                             LEFT JOIN reports r ON u.id = r.user_id 
                             WHERE u.type = 'citizen' 
                             GROUP BY u.id 
                             ORDER BY reports_count DESC 
                             LIMIT 10");

// Get top categories
$top_categories = $conn->query("SELECT category, COUNT(*) as count 
                                FROM reports 
                                WHERE user_id = $user_id 
                                GROUP BY category 
                                ORDER BY count DESC 
                                LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS - EXACT SAME THEME AS worker_dashboard.php (Green) -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - EXACT SAME AS WORKER DASHBOARD (Green gradient) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #2c3e50, #28a745);
            color: white;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.5rem;
        }
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            font-size: 14px;
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
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Bar - EXACT SAME AS WORKER DASHBOARD */
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
        
        /* Welcome Message - Green theme matching worker dashboard */
        .welcome-message {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-message::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-content h2 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-content p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* Stats Cards - EXACT SAME AS WORKER DASHBOARD */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
            border-left: 6px solid;
            width: 100%;
            height: 130px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: #2c3e50; }
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.progress { border-left-color: #3498db; }
        .stat-card.completed { border-left-color: #27ae60; }
        
        .stat-info h4 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-card.total .stat-info h4 { color: #2c3e50; }
        .stat-card.pending .stat-info h4 { color: #e67e22; }
        .stat-card.progress .stat-info h4 { color: #2980b9; }
        .stat-card.completed .stat-info h4 { color: #27ae60; }
        
        .stat-info p {
            margin-bottom: 0;
            font-size: 15px;
            font-weight: 600;
            color: #7f8c8d;
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .stat-card.total .stat-icon { background: #2c3e50; color: white; }
        .stat-card.pending .stat-icon { background: #f39c12; color: white; }
        .stat-card.progress .stat-icon { background: #3498db; color: white; }
        .stat-card.completed .stat-icon { background: #27ae60; color: white; }
        
        /* Quick Actions - Matching worker dashboard */
        .quick-actions {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-left: 6px solid #28a745;
        }
        
        .quick-actions h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-actions h5 i {
            color: #28a745;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .action-btn:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(40, 167, 69, 0.2);
        }
        
        .action-btn:hover .action-icon {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .action-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #28a745;
            font-size: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .action-btn:hover .action-icon {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .action-btn strong {
            font-size: 16px;
        }
        
        .action-btn div div {
            font-size: 12px;
            opacity: 0.8;
        }
        
        /* Recent Reports Section - EXACT SAME AS WORKER DASHBOARD */
        .reports-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 15px 20px;
        }
        
        .table-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .table-header h5 i {
            margin-right: 10px;
        }
        
        .table-body {
            padding: 0;
        }
        
        .report-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s;
        }
        
        .report-item:hover {
            background: #f8f9fa;
        }
        
        .report-item:last-child {
            border-bottom: none;
        }
        
        /* Status Badges - EXACT SAME AS WORKER DASHBOARD */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-pending { background: #fff3e0; color: #f57c00; }
        .badge-progress { background: #e3f2fd; color: #1976d2; }
        .badge-completed { background: #e8f5e9; color: #388e3c; }
        
        .report-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-progress {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        /* Top Categories - Matching worker dashboard */
        .top-categories {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-left: 6px solid #28a745;
        }
        
        .top-categories h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-categories h5 i {
            color: #28a745;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #28a745;
            font-size: 18px;
        }
        
        .category-name {
            flex: 1;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .category-count {
            font-weight: 700;
            color: #28a745;
            background: #e8f5e9;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        /* Leaderboard - Matching worker dashboard */
        .leaderboard {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-left: 6px solid #28a745;
        }
        
        .leaderboard h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .leaderboard h5 i {
            color: #28a745;
        }
        
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .leaderboard-item:hover {
            background: #e8f5e9;
            transform: translateX(5px);
        }
        
        .leaderboard-rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .rank-1 .leaderboard-rank {
            background: linear-gradient(135deg, #ffd700, #ffa500);
            color: white;
        }
        
        .rank-2 .leaderboard-rank {
            background: linear-gradient(135deg, #c0c0c0, #a0a0a0);
            color: white;
        }
        
        .rank-3 .leaderboard-rank {
            background: linear-gradient(135deg, #cd7f32, #b06d2a);
            color: white;
        }
        
        .leaderboard-item strong {
            color: #2c3e50;
            font-size: 14px;
        }
        
        .leaderboard-item div div {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        /* Alert Messages - EXACT SAME AS WORKER DASHBOARD */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #bdc3c7;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #7f8c8d;
        }
        
        /* Responsive - EXACT SAME AS WORKER DASHBOARD */
        @media (max-width: 1024px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3, .sidebar-header p, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 12px;
                margin: 5px;
            }
            
            .nav-link i {
                width: auto;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .top-bar h1 {
                font-size: 1.2rem;
            }
            
            .welcome-content h2 {
                font-size: 1.5rem;
            }
        }
        
        /* Animation */
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Sidebar - EXACT SAME AS WORKER DASHBOARD (Green gradient) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-trash-alt"></i> Swachh Bharat</h3>
            <p>Citizen Portal</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link active">
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
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar - EXACT SAME AS WORKER DASHBOARD -->
        <div class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <div class="user-info">
                <div>
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                    <div class="text-muted">Citizen</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Welcome Message - Green theme matching worker dashboard -->
        <div class="welcome-message fade-in">
            <div class="welcome-content">
                <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 🎉</h2>
                <p class="mb-0">Keep up the great work in making our city cleaner. Your contributions matter!</p>
            </div>
        </div>
        
        <!-- Statistics Cards - EXACT SAME AS WORKER DASHBOARD -->
        <div class="stats-row fade-in">
            <div class="stat-card total">
                <div class="stat-info">
                    <h4><?php echo $total_reports; ?></h4>
                    <p>Total Reports</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-info">
                    <h4><?php echo $pending_reports; ?></h4>
                    <p>Pending</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card progress">
                <div class="stat-info">
                    <h4><?php echo $in_progress_reports; ?></h4>
                    <p>In Progress</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card completed">
                <div class="stat-info">
                    <h4><?php echo $completed_reports; ?></h4>
                    <p>Completed</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        
        <div class="row fade-in">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Recent Reports -->
                <div class="reports-table mb-4">
                    <div class="table-header">
                        <h5><i class="fas fa-history"></i> Recent Reports</h5>
                    </div>
                    <div class="table-body">
                        <?php if($recent_reports && $recent_reports->num_rows > 0): ?>
                            <?php while($report = $recent_reports->fetch_assoc()): ?>
                                <div class="report-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($report['title']); ?></h6>
                                            <p class="text-muted mb-0" style="font-size: 12px;">
                                                <i class="fas fa-map-marker-alt" style="color: #28a745;"></i> 
                                                <?php echo htmlspecialchars($report['location']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge" style="background: #e9ecef; color: #2c3e50;">
                                                <?php echo ucfirst($report['category']); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <?php
                                            $status_class = '';
                                            $status_text = $report['status'];
                                            if($report['status'] == 'pending') $status_class = 'status-pending';
                                            elseif($report['status'] == 'in_progress') $status_class = 'status-progress';
                                            elseif($report['status'] == 'resolved') {
                                                $status_class = 'status-completed';
                                                $status_text = 'completed';
                                            } else {
                                                $status_class = 'status-completed';
                                            }
                                            ?>
                                            <span class="report-status <?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $status_text)); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p class="text-muted">No reports yet. Start by reporting an issue!</p>
                                <a href="report.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus-circle"></i> Report First Issue
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top Categories -->
                <div class="top-categories mb-4">
                    <h5><i class="fas fa-chart-pie"></i> Top Report Categories</h5>
                    <?php if($top_categories && $top_categories->num_rows > 0): ?>
                        <?php while($category = $top_categories->fetch_assoc()): ?>
                            <div class="category-item">
                                <div class="category-icon">
                                    <?php
                                    $icons = [
                                        'garbage' => 'fas fa-trash',
                                        'road' => 'fas fa-road',
                                        'roads' => 'fas fa-road',
                                        'water' => 'fas fa-tint',
                                        'electricity' => 'fas fa-bolt',
                                        'parks' => 'fas fa-tree',
                                        'other' => 'fas fa-question-circle'
                                    ];
                                    $cat = strtolower($category['category']);
                                    $icon = isset($icons[$cat]) ? $icons[$cat] : 'fas fa-question-circle';
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div class="category-name">
                                    <?php echo ucfirst($category['category']); ?>
                                </div>
                                <div class="category-count">
                                    <?php echo $category['count']; ?> reports
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <p class="text-muted">No category data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="quick-actions mb-4">
                    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    <a href="report.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <strong>Report Issue</strong>
                            <div>Report a new problem</div>
                        </div>
                    </a>
                    <a href="my_reports.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div>
                            <strong>View Reports</strong>
                            <div>Check your submissions</div>
                        </div>
                    </a>
                    <a href="rewards.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div>
                            <strong>Claim Rewards</strong>
                            <div>Redeem your points</div>
                        </div>
                    </a>
                    <a href="profile.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div>
                            <strong>Update Profile</strong>
                            <div>Edit your information</div>
                        </div>
                    </a>
                </div>
                
                <!-- Leaderboard -->
                <div class="leaderboard">
                    <h5><i class="fas fa-trophy"></i> Top Contributors</h5>
                    <?php if($leaderboard && $leaderboard->num_rows > 0): ?>
                        <?php $rank = 1; ?>
                        <?php while($contributor = $leaderboard->fetch_assoc()): ?>
                            <div class="leaderboard-item rank-<?php echo $rank; ?>">
                                <div class="leaderboard-rank"><?php echo $rank; ?></div>
                                <div style="flex: 1;">
                                    <strong><?php echo htmlspecialchars($contributor['name']); ?></strong>
                                    <div style="font-size: 11px; color: #7f8c8d;">
                                        <?php echo $contributor['reports_count']; ?> reports
                                    </div>
                                </div>
                            </div>
                            <?php $rank++; ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p class="text-muted">No contributors yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS (Keep your existing JavaScript) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to stats cards
            const statsCards = document.querySelectorAll('.stat-card');
            statsCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Update current time
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                const dateString = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                const timeElement = document.querySelector('.current-time');
                if(timeElement) {
                    timeElement.innerHTML = `<i class="far fa-clock"></i> ${timeString} - ${dateString}`;
                }
            }
            
            // Update time every minute
            updateTime();
            setInterval(updateTime, 60000);
            
            // Add current time to top bar
            const topBar = document.querySelector('.top-bar');
            if(topBar) {
                const timeElement = document.createElement('div');
                timeElement.className = 'current-time text-muted';
                timeElement.style.fontSize = '14px';
                topBar.appendChild(timeElement);
                updateTime();
            }
            
            // Add motivational messages
            const messages = [
                "Every report makes our city cleaner!",
                "Thank you for your civic contribution!",
                "Together we can make a difference!",
                "Your efforts are making an impact!",
                "Keep up the great work!"
            ];
            
            const welcomeMessage = document.querySelector('.welcome-message p');
            if(welcomeMessage && <?php echo $total_reports; ?> > 0) {
                const randomMessage = messages[Math.floor(Math.random() * messages.length)];
                welcomeMessage.innerHTML = randomMessage + " You've submitted <?php echo $total_reports; ?> reports so far!";
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>