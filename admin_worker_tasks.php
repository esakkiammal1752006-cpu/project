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

// Get worker ID from URL
$worker_id = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;

if ($worker_id == 0) {
    header("Location: admin_workers.php");
    exit();
}

// Get worker details
$worker_query = $conn->query("SELECT * FROM users WHERE id = '$worker_id' AND type = 'worker'");
if ($worker_query->num_rows == 0) {
    header("Location: admin_workers.php");
    exit();
}
$worker = $worker_query->fetch_assoc();

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$where_conditions = ["r.assigned_to = '$worker_id'"];

if ($status_filter != 'all') {
    $where_conditions[] = "r.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($category_filter != 'all') {
    $where_conditions[] = "r.category = '" . $conn->real_escape_string($category_filter) . "'";
}
if ($priority_filter != 'all') {
    $where_conditions[] = "r.priority = '" . $conn->real_escape_string($priority_filter) . "'";
}
if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $where_conditions[] = "(r.title LIKE '%$search%' OR r.description LIKE '%$search%' OR r.location LIKE '%$search%' OR u.name LIKE '%$search%')";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get worker's assigned tasks with citizen details
$tasks = $conn->query("
    SELECT r.*, 
           u.name as citizen_name, 
           u.email as citizen_email,
           u.phone as citizen_phone,
           u.address as citizen_address
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    $where_sql
    ORDER BY 
        CASE r.priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        r.created_at DESC
");

// Get task statistics
$total_tasks = $conn->query("SELECT COUNT(*) as total FROM reports WHERE assigned_to = '$worker_id'")->fetch_assoc()['total'];
$pending_tasks = $conn->query("SELECT COUNT(*) as total FROM reports WHERE assigned_to = '$worker_id' AND status='pending'")->fetch_assoc()['total'];
$in_progress_tasks = $conn->query("SELECT COUNT(*) as total FROM reports WHERE assigned_to = '$worker_id' AND status='in_progress'")->fetch_assoc()['total'];
$completed_tasks = $conn->query("SELECT COUNT(*) as total FROM reports WHERE assigned_to = '$worker_id' AND status='completed'")->fetch_assoc()['total'];

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM reports WHERE assigned_to = '$worker_id' ORDER BY category");

// Get task updates for each report
$task_updates = [];
if ($tasks->num_rows > 0) {
    $tasks->data_seek(0);
    while($task = $tasks->fetch_assoc()) {
        $updates = $conn->query("
            SELECT ru.*, u.name as updater_name 
            FROM report_updates ru 
            LEFT JOIN users u ON ru.user_id = u.id 
            WHERE ru.report_id = '{$task['id']}' 
            ORDER BY ru.created_at DESC
        ");
        $task_updates[$task['id']] = $updates;
    }
    $tasks->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Worker Tasks</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS - Matching admin theme -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - Same as admin pages */
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
        
        /* Top Bar */
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
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
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
            font-size: 18px;
        }
        
        /* Worker Header */
        .worker-header {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-left: 6px solid #f39c12;
        }
        
        .worker-title {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .worker-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
        }
        
        .worker-info h2 {
            margin: 0 0 5px 0;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .worker-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .worker-meta i {
            color: #28a745;
            width: 20px;
        }
        
        .back-button {
            margin-left: auto;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        
        /* Stats Cards */
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
        
        /* Filters Section */
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
            border-color: #1e7e34;
        }
        
        .btn-outline-secondary {
            width: 100%;
        }
        
        /* Tasks Table Section */
        .tasks-section {
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
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-in_progress {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .priority-high {
            background: #fee;
            color: #c0392b;
        }
        
        .priority-medium {
            background: #fff3e0;
            color: #e67e22;
        }
        
        .priority-low {
            background: #e3f2fd;
            color: #2980b9;
        }
        
        /* Category Badge */
        .category-badge {
            background: #e9ecef;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Action Buttons */
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
        
        /* Citizen Info */
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
            width: 12px;
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
        
        /* Responsive */
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
                grid-template-columns: 1fr 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .worker-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .back-button {
                margin-left: 0;
                width: 100%;
            }
            
            .btn-back {
                display: block;
                text-align: center;
            }
        }
        
        @media (max-width: 576px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-trash-alt"></i> Admin</h3>
            <p>Municipal Corporation</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_reports.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>All Reports</span>
            </a>
            <a href="admin_users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="admin_workers.php" class="nav-link active">
                <i class="fas fa-tools"></i>
                <span>Workers</span>
            </a>
            <a href="admin_settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h4 class="mb-0"><i class="fas fa-tasks"></i> Worker Tasks</h4>
            <div class="admin-info">
                <div>
                    <strong><?php echo htmlspecialchars($admin_username); ?></strong>
                    <div class="text-muted" style="font-size: 12px;">Administrator</div>
                </div>
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Worker Header -->
        <div class="worker-header">
            <div class="worker-title">
                <div class="worker-icon">
                    <i class="fas fa-user-hard-hat"></i>
                </div>
                <div class="worker-info">
                    <h2><?php echo htmlspecialchars($worker['name']); ?></h2>
                    <div class="worker-meta">
                        <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($worker['user_id']); ?> • 
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($worker['email']); ?> • 
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($worker['phone']); ?>
                    </div>
                </div>
                <div class="back-button">
                    <a href="admin_workers.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Workers
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-info">
                    <h4><?php echo $total_tasks; ?></h4>
                    <p>Total Tasks</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-info">
                    <h4><?php echo $pending_tasks; ?></h4>
                    <p>Pending</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card progress">
                <div class="stat-info">
                    <h4><?php echo $in_progress_tasks; ?></h4>
                    <p>In Progress</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card completed">
                <div class="stat-info">
                    <h4><?php echo $completed_tasks; ?></h4>
                    <p>Completed</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="worker_id" value="<?php echo $worker_id; ?>">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
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
                        <label class="filter-label">Priority</label>
                        <select class="form-select" name="priority" onchange="this.form.submit()">
                            <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                            <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search tasks..." 
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
                        <a href="admin_worker_tasks.php?worker_id=<?php echo $worker_id; ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tasks Table -->
        <div class="tasks-section">
            <div class="section-header">
                <h5><i class="fas fa-list"></i> Assigned Tasks</h5>
                <span class="badge">
                    <?php echo $tasks ? $tasks->num_rows : 0; ?> tasks found
                </span>
            </div>
            
            <div class="table-container">
                <table id="tasksTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Citizen</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tasks && $tasks->num_rows > 0): ?>
                            <?php while($task = $tasks->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $task['id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($task['title'], 0, 30)); ?></strong>
                                        <?php if(strlen($task['title']) > 30): ?>...<?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="citizen-name"><?php echo htmlspecialchars($task['citizen_name'] ?? 'Unknown'); ?></div>
                                        <div class="citizen-contact">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($task['citizen_phone'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="category-badge"><?php echo ucfirst($task['category']); ?></span>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($task['location']); ?>">
                                            <?php echo htmlspecialchars(substr($task['location'], 0, 15)); ?>...
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $task['status']; ?>">
                                            <?php 
                                                if($task['status'] == 'pending') echo 'Pending';
                                                elseif($task['status'] == 'in_progress') echo 'In Progress';
                                                elseif($task['status'] == 'completed') echo 'Completed';
                                                else echo ucfirst($task['status']);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d M', strtotime($task['created_at'])); ?>
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
                                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                    <h5>No Tasks Found</h5>
                                    <p class="text-muted">This worker has no assigned tasks</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt"></i> Task Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history"></i> Task Updates
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
            $('#tasksTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search tasks..."
                }
            });
        });
        
        // Task details data from PHP
        const tasks = <?php 
            $tasks->data_seek(0);
            $task_data = [];
            while($row = $tasks->fetch_assoc()) {
                $task_data[$row['id']] = $row;
            }
            echo json_encode($task_data);
        ?>;
        
        const taskUpdates = <?php 
            $updates_data = [];
            foreach($task_updates as $report_id => $updates) {
                $updates_array = [];
                if ($updates->num_rows > 0) {
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
                                                <td><span class="category-badge">${ucfirst(task.category)}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Priority:</th>
                                                <td><span class="priority-badge priority-${task.priority}">${ucfirst(task.priority)}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td><span class="status-badge status-${task.status}">${ucfirst(task.status.replace('_', ' '))}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Location:</th>
                                                <td>${escapeHtml(task.location)}</td>
                                            </tr>
                                            <tr>
                                                <th>Reported Date:</th>
                                                <td>${new Date(task.created_at).toLocaleString()}</td>
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
                            ${update.status_change ? `<span class="update-status status-${update.status_change}">Status changed to: ${ucfirst(update.status_change.replace('_', ' '))}</span>` : ''}
                        </div>
                    `;
                });
            } else {
                updatesHtml += `
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No updates yet for this task</p>
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