<?php
// Start session and database connection
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'worker') {
    header("Location: login.php");
    exit();
}

// Database configuration
$conn = new mysqli("localhost", "root", "", "swachh", 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$worker_id = $_SESSION['user_id'];
$worker_name = $_SESSION['user_name'];

// Get filter parameters
$month_filter = isset($_GET['month']) ? $_GET['month'] : 'all';
$year_filter = isset($_GET['year']) ? $_GET['year'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters - FIXED: Removed completed_at dependency
$where_conditions = ["r.assigned_to = '$worker_id'"];

// Add status filter for completed tasks only (history shows completed tasks)
$where_conditions[] = "r.status = 'completed'";

// Use updated_at as completion time if completed_at doesn't exist
// Check if completed_at column exists
$column_check = $conn->query("SHOW COLUMNS FROM reports LIKE 'completed_at'");
$has_completed_at = ($column_check && $column_check->num_rows > 0);

if ($month_filter != 'all') {
    if ($has_completed_at) {
        $where_conditions[] = "MONTH(r.completed_at) = '" . $conn->real_escape_string($month_filter) . "'";
    } else {
        $where_conditions[] = "MONTH(r.updated_at) = '" . $conn->real_escape_string($month_filter) . "'";
    }
}
if ($year_filter != 'all') {
    if ($has_completed_at) {
        $where_conditions[] = "YEAR(r.completed_at) = '" . $conn->real_escape_string($year_filter) . "'";
    } else {
        $where_conditions[] = "YEAR(r.updated_at) = '" . $conn->real_escape_string($year_filter) . "'";
    }
}
if ($category_filter != 'all') {
    $where_conditions[] = "r.category = '" . $conn->real_escape_string($category_filter) . "'";
}
if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $where_conditions[] = "(r.title LIKE '%$search%' OR r.description LIKE '%$search%' OR r.location LIKE '%$search%' OR u.name LIKE '%$search%')";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get worker's completed tasks with citizen details - FIXED: Handle completed_at
if ($has_completed_at) {
    $completed_tasks = $conn->query("
        SELECT r.*, 
               u.name as citizen_name, 
               u.email as citizen_email,
               u.phone as citizen_phone,
               u.address as citizen_address,
               DATEDIFF(r.completed_at, r.created_at) as completion_days
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        $where_sql
        ORDER BY r.completed_at DESC, r.created_at DESC
    ");
} else {
    // Use updated_at as fallback
    $completed_tasks = $conn->query("
        SELECT r.*, 
               u.name as citizen_name, 
               u.email as citizen_email,
               u.phone as citizen_phone,
               u.address as citizen_address,
               DATEDIFF(r.updated_at, r.created_at) as completion_days
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        $where_sql
        ORDER BY r.updated_at DESC, r.created_at DESC
    ");
}

// Get statistics
$total_completed = $conn->query("SELECT COUNT(*) as total FROM reports WHERE assigned_to='$worker_id' AND status='completed'")->fetch_assoc()['total'];

// Get monthly stats - FIXED: Handle completed_at
if ($has_completed_at) {
    $monthly_stats = $conn->query("
        SELECT 
            MONTH(completed_at) as month,
            YEAR(completed_at) as year,
            COUNT(*) as count
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND completed_at IS NOT NULL
        GROUP BY YEAR(completed_at), MONTH(completed_at)
        ORDER BY year DESC, month DESC
        LIMIT 6
    ");
} else {
    $monthly_stats = $conn->query("
        SELECT 
            MONTH(updated_at) as month,
            YEAR(updated_at) as year,
            COUNT(*) as count
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND updated_at IS NOT NULL
        GROUP BY YEAR(updated_at), MONTH(updated_at)
        ORDER BY year DESC, month DESC
        LIMIT 6
    ");
}

// Get category stats
$category_stats = $conn->query("
    SELECT 
        category,
        COUNT(*) as count
    FROM reports 
    WHERE assigned_to='$worker_id' AND status='completed'
    GROUP BY category
    ORDER BY count DESC
");

// Get average completion time - FIXED: Handle completed_at
if ($has_completed_at) {
    $avg_time = $conn->query("
        SELECT AVG(DATEDIFF(completed_at, created_at)) as avg_days
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND completed_at IS NOT NULL
    ")->fetch_assoc()['avg_days'];
} else {
    $avg_time = $conn->query("
        SELECT AVG(DATEDIFF(updated_at, created_at)) as avg_days
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND updated_at IS NOT NULL
    ")->fetch_assoc()['avg_days'];
}
$avg_completion_days = $avg_time ? round($avg_time, 1) : 0;

// Get this month's completed count - FIXED: Handle completed_at
if ($has_completed_at) {
    $this_month = $conn->query("
        SELECT COUNT(*) as total 
        FROM reports 
        WHERE assigned_to='$worker_id' 
        AND status='completed' 
        AND MONTH(completed_at) = MONTH(CURRENT_DATE())
        AND YEAR(completed_at) = YEAR(CURRENT_DATE())
    ")->fetch_assoc()['total'];
} else {
    $this_month = $conn->query("
        SELECT COUNT(*) as total 
        FROM reports 
        WHERE assigned_to='$worker_id' 
        AND status='completed' 
        AND MONTH(updated_at) = MONTH(CURRENT_DATE())
        AND YEAR(updated_at) = YEAR(CURRENT_DATE())
    ")->fetch_assoc()['total'];
}

// Get unique months and years for filters - FIXED: Handle completed_at
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if ($has_completed_at) {
    $years = $conn->query("
        SELECT DISTINCT YEAR(completed_at) as year 
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND completed_at IS NOT NULL
        ORDER BY year DESC
    ");
} else {
    $years = $conn->query("
        SELECT DISTINCT YEAR(updated_at) as year 
        FROM reports 
        WHERE assigned_to='$worker_id' AND status='completed' AND updated_at IS NOT NULL
        ORDER BY year DESC
    ");
}

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM reports WHERE assigned_to='$worker_id' ORDER BY category");

// Get task updates for completed tasks
$task_updates = [];
$updates_table_check = $conn->query("SHOW TABLES LIKE 'report_updates'");
$has_updates_table = ($updates_table_check && $updates_table_check->num_rows > 0);

if ($completed_tasks && $completed_tasks->num_rows > 0 && $has_updates_table) {
    $completed_tasks->data_seek(0);
    while($task = $completed_tasks->fetch_assoc()) {
        $updates = $conn->query("
            SELECT ru.*, u.name as updater_name 
            FROM report_updates ru 
            LEFT JOIN users u ON ru.user_id = u.id 
            WHERE ru.report_id = '{$task['id']}' 
            ORDER BY ru.created_at DESC
        ");
        $task_updates[$task['id']] = $updates;
    }
    $completed_tasks->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Work History</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS - EXACT SAME THEME AS admin_reports.php (Green) -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - EXACT SAME AS ADMIN REPORTS (Green gradient) */
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
        }
        
        .sidebar-header p {
            font-size: 12px;
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
        
        /* Top Bar - EXACT SAME AS ADMIN REPORTS */
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
        
        .worker-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .worker-avatar {
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
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-left: 6px solid #28a745;
        }
        
        .page-header h2 {
            margin: 0 0 5px 0;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .page-header p {
            margin: 0;
            color: #7f8c8d;
        }
        
        .page-header i {
            color: #28a745;
            margin-right: 10px;
        }
        
        /* Stats Cards - EXACT SAME AS ADMIN REPORTS */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        .stat-card.avg-time { border-left-color: #f39c12; }
        .stat-card.this-month { border-left-color: #27ae60; }
        
        .stat-info h4 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-card.total .stat-info h4 { color: #2c3e50; }
        .stat-card.avg-time .stat-info h4 { color: #e67e22; }
        .stat-card.this-month .stat-info h4 { color: #27ae60; }
        
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
        .stat-card.avg-time .stat-icon { background: #f39c12; color: white; }
        .stat-card.this-month .stat-icon { background: #27ae60; color: white; }
        
        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .chart-card h5 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .chart-card h5 i {
            color: #28a745;
            margin-right: 8px;
        }
        
        /* Filters Section - EXACT SAME AS ADMIN REPORTS */
        .filters-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-select, .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.3s;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
            border-color: #28a745;
            width: 100%;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-outline-secondary {
            width: 100%;
        }
        
        /* History Table Section - EXACT SAME AS ADMIN REPORTS */
        .history-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-header h5 i {
            margin-right: 10px;
        }
        
        .section-header .badge {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
            font-size: 12px;
            padding: 5px 12px;
        }
        
        .table-container {
            padding: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
            white-space: nowrap;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Status Badges - EXACT SAME AS ADMIN REPORTS */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-completed { background: #e8f5e9; color: #388e3c; }
        
        .priority-high { color: #dc3545; font-weight: 600; }
        .priority-medium { color: #f57c00; font-weight: 600; }
        .priority-low { color: #28a745; font-weight: 600; }
        
        /* Category Badge */
        .category-badge {
            background: #e9ecef;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Completion Badge */
        .completion-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background: #d4edda;
            color: #155724;
        }
        
        /* Action Buttons - EXACT SAME AS ADMIN REPORTS */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 5px;
            white-space: nowrap;
            border: none;
            color: white;
            cursor: pointer;
        }
        
        .btn-action i {
            margin-right: 3px;
        }
        
        .btn-view {
            background: #17a2b8;
        }
        
        .btn-view:hover {
            background: #138496;
        }
        
        .btn-updates {
            background: #6f42c1;
        }
        
        .btn-updates:hover {
            background: #5e34b1;
        }
        
        /* Citizen Info - EXACT SAME AS ADMIN REPORTS */
        .citizen-info {
            display: flex;
            flex-direction: column;
        }
        
        .citizen-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .citizen-contact {
            font-size: 11px;
            color: #6c757d;
        }
        
        .citizen-contact i {
            color: #28a745;
            width: 14px;
        }
        
        /* Completion Time */
        .completion-time {
            font-size: 11px;
            color: #6c757d;
        }
        
        .completion-time i {
            color: #28a745;
            margin-right: 3px;
        }
        
        /* Updates Timeline Modal */
        .updates-timeline {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .update-item {
            border-left: 3px solid #28a745;
            padding: 10px 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .update-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .update-user {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .update-time {
            color: #6c757d;
        }
        
        .update-text {
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .update-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Alert Messages - EXACT SAME AS ADMIN REPORTS */
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-state h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #7f8c8d;
        }
        
        /* Responsive - EXACT SAME AS ADMIN REPORTS */
        @media (max-width: 1024px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-row {
                grid-template-columns: 1fr;
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
            
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - EXACT SAME AS ADMIN REPORTS (Green gradient) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tools"></i> Worker</h3>
            <p>Municipal Corporation</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="worker.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="worker_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>
                <span>My Tasks</span>
            </a>
            <a href="worker_history.php" class="nav-link active">
                <i class="fas fa-history"></i>
                <span>History</span>
            </a>
            <a href="worker_profile.php" class="nav-link">
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
        <!-- Top Bar - EXACT SAME AS ADMIN REPORTS -->
        <div class="top-bar">
            <h4 class="mb-0"><i class="fas fa-history"></i> Work History</h4>
            <div class="worker-info">
                <div>
                    <strong><?php echo htmlspecialchars($worker_name); ?></strong>
                    <div class="text-muted" style="font-size: 12px;">Municipal Worker</div>
                </div>
                <div class="worker-avatar">
                    <?php echo strtoupper(substr($worker_name, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-clipboard-check"></i> Completed Work History</h2>
            <p>View all tasks you have successfully completed</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-info">
                    <h4><?php echo $total_completed; ?></h4>
                    <p>Total Completed</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card avg-time">
                <div class="stat-info">
                    <h4><?php echo $avg_completion_days; ?></h4>
                    <p>Avg. Completion Days</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card this-month">
                <div class="stat-info">
                    <h4><?php echo $this_month; ?></h4>
                    <p>Completed This Month</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <?php if ($monthly_stats && $monthly_stats->num_rows > 0 && $category_stats && $category_stats->num_rows > 0): ?>
        <div class="charts-row">
            <?php if ($monthly_stats && $monthly_stats->num_rows > 0): ?>
            <div class="chart-card">
                <h5><i class="fas fa-chart-line"></i> Monthly Completion</h5>
                <canvas id="monthlyChart" style="max-height: 250px;"></canvas>
            </div>
            <?php endif; ?>
            
            <?php if ($category_stats && $category_stats->num_rows > 0): ?>
            <div class="chart-card">
                <h5><i class="fas fa-chart-pie"></i> By Category</h5>
                <canvas id="categoryChart" style="max-height: 250px;"></canvas>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Filters - EXACT SAME AS ADMIN REPORTS -->
        <div class="filters-section">
            <form method="GET" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Month</label>
                        <select class="form-select" name="month" onchange="this.form.submit()">
                            <option value="all" <?php echo $month_filter == 'all' ? 'selected' : ''; ?>>All Months</option>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo $month_filter == $num ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Year</label>
                        <select class="form-select" name="year" onchange="this.form.submit()">
                            <option value="all" <?php echo $year_filter == 'all' ? 'selected' : ''; ?>>All Years</option>
                            <?php 
                            if ($years && $years->num_rows > 0) {
                                while($year = $years->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php 
                            if ($categories && $categories->num_rows > 0) {
                                while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($cat['category'])); ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search history..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="worker_history.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- History Table - EXACT SAME AS ADMIN REPORTS -->
        <div class="history-section">
            <div class="section-header">
                <h5><i class="fas fa-history"></i> Completed Tasks History</h5>
                <span class="badge">
                    <?php echo $completed_tasks ? $completed_tasks->num_rows : 0; ?> records found
                </span>
            </div>
            
            <div class="table-container">
                <table id="historyTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Citizen</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Priority</th>
                            <th>Completed</th>
                            <th>Time Taken</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($completed_tasks && $completed_tasks->num_rows > 0): ?>
                            <?php while($task = $completed_tasks->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $task['id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($task['title'], 0, 30)); ?></strong>
                                        <?php if(strlen($task['title']) > 30): ?>...<?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="citizen-info">
                                            <span class="citizen-name"><?php echo htmlspecialchars($task['citizen_name'] ?? 'Unknown'); ?></span>
                                            <span class="citizen-contact">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($task['citizen_phone'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #e9ecef;"><?php echo ucfirst($task['category']); ?></span>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($task['location']); ?>">
                                            <?php echo htmlspecialchars(substr($task['location'], 0, 15)); ?>...
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-<?php echo $task['priority']; ?>">
                                            <i class="fas fa-flag"></i> <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($has_completed_at && $task['completed_at']) {
                                            echo date('d M Y', strtotime($task['completed_at']));
                                        } else {
                                            echo date('d M Y', strtotime($task['updated_at']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($task['completion_days'] !== null): ?>
                                            <span class="completion-badge">
                                                <i class="fas fa-hourglass-half"></i> <?php echo $task['completion_days']; ?> days
                                            </span>
                                            <div class="completion-time">
                                                <i class="far fa-calendar"></i> 
                                                <?php echo date('d M', strtotime($task['created_at'])); ?> - 
                                                <?php 
                                                if ($has_completed_at && $task['completed_at']) {
                                                    echo date('d M', strtotime($task['completed_at']));
                                                } else {
                                                    echo date('d M', strtotime($task['updated_at']));
                                                }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewTaskDetails(<?php echo $task['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn-action btn-updates" 
                                                    onclick="viewTaskUpdates(<?php echo $task['id']; ?>)"
                                                    title="View Updates">
                                                <i class="fas fa-history"></i> Updates
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <h5>No History Found</h5>
                                        <p class="text-muted">No completed tasks match your filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Task Details Modal - Green theme -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #28a745);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-file-alt"></i> Task Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="taskDetailsBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading task details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Task Updates Modal -->
    <div class="modal fade" id="taskUpdatesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #28a745);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history"></i> Task Updates
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="taskUpdatesBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading updates...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#historyTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                order: [[6, 'desc']], // Sort by completed date descending
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search history..."
                }
            });
            
            <?php if ($monthly_stats && $monthly_stats->num_rows > 0): ?>
                // Monthly chart
                const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php 
                            $monthly_stats->data_seek(0);
                            $labels = [];
                            $counts = [];
                            while($row = $monthly_stats->fetch_assoc()) {
                                $labels[] = "'" . $months[$row['month']] . " " . $row['year'] . "'";
                                $counts[] = $row['count'];
                            }
                            echo implode(',', $labels);
                        ?>],
                        datasets: [{
                            label: 'Completed Tasks',
                            data: [<?php echo implode(',', $counts); ?>],
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
            
            <?php if ($category_stats && $category_stats->num_rows > 0): ?>
                // Category chart
                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php 
                            $category_stats->data_seek(0);
                            $labels = [];
                            $counts = [];
                            while($row = $category_stats->fetch_assoc()) {
                                $labels[] = "'" . ucfirst($row['category']) . "'";
                                $counts[] = $row['count'];
                            }
                            echo implode(',', $labels);
                        ?>],
                        datasets: [{
                            data: [<?php echo implode(',', $counts); ?>],
                            backgroundColor: [
                                '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
                                '#6f42c1', '#fd7e14', '#20c997', '#e83e8c'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
        
        // Task data from PHP
        const tasks = <?php 
            $completed_tasks->data_seek(0);
            $task_data = [];
            while($row = $completed_tasks->fetch_assoc()) {
                $task_data[$row['id']] = $row;
            }
            echo json_encode($task_data);
        ?>;
        
        const taskUpdates = <?php 
            $updates_data = [];
            foreach($task_updates as $report_id => $updates) {
                $updates_array = [];
                if ($updates && $updates->num_rows > 0) {
                    while($update = $updates->fetch_assoc()) {
                        $updates_array[] = $update;
                    }
                }
                $updates_data[$report_id] = $updates_array;
            }
            echo json_encode($updates_data);
        ?>;
        
        function viewTaskDetails(taskId) {
            const task = tasks[taskId];
            const modal = new bootstrap.Modal(document.getElementById('taskDetailsModal'));
            const modalBody = document.getElementById('taskDetailsBody');
            
            if (task) {
                modalBody.innerHTML = `
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Report Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="30%">Report ID:</th>
                                                <td><strong>#${task.id}</strong></td>
                                            </tr>
                                            <tr>
                                                <th>Title:</th>
                                                <td>${escapeHtml(task.title)}</td>
                                            </tr>
                                            <tr>
                                                <th>Description:</th>
                                                <td>${escapeHtml(task.description || 'N/A')}</td>
                                            </tr>
                                            <tr>
                                                <th>Category:</th>
                                                <td><span class="badge" style="background: #e9ecef;">${ucfirst(task.category)}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Priority:</th>
                                                <td><span class="priority-${task.priority}">${ucfirst(task.priority)}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Location:</th>
                                                <td>${escapeHtml(task.location)}</td>
                                            </tr>
                                            <tr>
                                                <th>Reported Date:</th>
                                                <td>${new Date(task.created_at).toLocaleString()}</td>
                                            </tr>
                                            <tr>
                                                <th>Completed Date:</th>
                                                <td>${task.completed_at ? new Date(task.completed_at).toLocaleString() : (task.updated_at ? new Date(task.updated_at).toLocaleString() : 'N/A')}</td>
                                            </tr>
                                            <tr>
                                                <th>Time Taken:</th>
                                                <td><span class="completion-badge">${task.completion_days ? task.completion_days + ' days' : 'N/A'}</span></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-user"></i> Citizen Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="30%">Name:</th>
                                                <td>${escapeHtml(task.citizen_name || 'N/A')}</td>
                                            </tr>
                                            <tr>
                                                <th>Email:</th>
                                                <td>${escapeHtml(task.citizen_email || 'N/A')}</td>
                                            </tr>
                                            <tr>
                                                <th>Phone:</th>
                                                <td>${escapeHtml(task.citizen_phone || 'N/A')}</td>
                                            </tr>
                                            <tr>
                                                <th>Address:</th>
                                                <td>${escapeHtml(task.citizen_address || 'N/A')}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            modal.show();
        }
        
        function viewTaskUpdates(taskId) {
            const updates = taskUpdates[taskId] || [];
            const modal = new bootstrap.Modal(document.getElementById('taskUpdatesModal'));
            const modalBody = document.getElementById('taskUpdatesBody');
            
            let updatesHtml = '<div class="updates-timeline">';
            
            if (updates.length > 0) {
                updates.forEach(update => {
                    updatesHtml += `
                        <div class="update-item">
                            <div class="update-header">
                                <span class="update-user"><i class="fas fa-user"></i> ${escapeHtml(update.updater_name || 'System')}</span>
                                <span class="update-time"><i class="far fa-clock"></i> ${new Date(update.created_at).toLocaleString()}</span>
                            </div>
                            <div class="update-text">${escapeHtml(update.update_text || 'No update text')}</div>
                            ${update.status_change ? `<span class="update-status badge badge-${update.status_change}">Status: ${ucfirst(update.status_change.replace('_', ' '))}</span>` : ''}
                        </div>
                    `;
                });
            } else {
                updatesHtml += `
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No updates for this task</p>
                    </div>
                `;
            }
            
            updatesHtml += '</div>';
            modalBody.innerHTML = updatesHtml;
            modal.show();
        }
        
        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function ucfirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert-dismissible').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>