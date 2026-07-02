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

// ✅ CREATE notifications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle worker assignment - FIXED with proper escaping
if (isset($_POST['assign_worker'])) {
    $report_id = $conn->real_escape_string($_POST['report_id']);
    $worker_id = $conn->real_escape_string($_POST['worker_id']);
    
    $update_sql = "UPDATE reports SET assigned_to = '$worker_id', status = 'in_progress' WHERE id = '$report_id'";
    if ($conn->query($update_sql)) {
        
        // ✅ Get report title
        $report_title_query = $conn->query("SELECT title FROM reports WHERE id = '$report_id'");
        $report_title = "";
        if ($report_title_query && $report_title_query->num_rows > 0) {
            $report = $report_title_query->fetch_assoc();
            $report_title = addslashes($report['title']);
        }
        
        // ✅ Use addslashes for the entire message
        $title = "New Task Assigned";
        $message = "A new task '$report_title' (Report #$report_id) has been assigned to you.";
        
        // ✅ Escape the message for SQL
        $title_safe = $conn->real_escape_string($title);
        $message_safe = $conn->real_escape_string($message);
        
        // ✅ Insert into notifications
        $insert_notification = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                VALUES ('$worker_id', '$title_safe', '$message_safe', 'task_assigned', NOW())";
        
        if ($conn->query($insert_notification)) {
            $success = "Worker assigned successfully! Notification sent to worker.";
        } else {
            $success = "Worker assigned successfully!";
        }
        
    } else {
        $error = "Error assigning worker: " . $conn->error;
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $report_id = $conn->real_escape_string($_POST['report_id']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $update_sql = "UPDATE reports SET status = '$status' WHERE id = '$report_id'";
    if ($conn->query($update_sql)) {
        $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'rewards_points'");
        
        if ($status == 'completed' && $column_check && $column_check->num_rows > 0) {
            $report_query = $conn->query("SELECT user_id FROM reports WHERE id='$report_id'");
            if ($report_query && $report_query->num_rows > 0) {
                $report = $report_query->fetch_assoc();
                $conn->query("UPDATE users SET rewards_points = IFNULL(rewards_points, 0) + 25 WHERE id='{$report['user_id']}'");
                
                $rewards_check = $conn->query("SHOW TABLES LIKE 'rewards'");
                if ($rewards_check && $rewards_check->num_rows > 0) {
                    $conn->query("INSERT INTO rewards (user_id, points, reason, created_at) 
                                  VALUES ('{$report['user_id']}', 25, 'Report #$report_id completed', NOW())");
                }
            }
        }
        $success = "Status updated successfully!";
    } else {
        $error = "Error updating status: " . $conn->error;
    }
}

// Handle report deletion
if (isset($_GET['delete'])) {
    $report_id = $conn->real_escape_string($_GET['delete']);
    $delete_sql = "DELETE FROM reports WHERE id = '$report_id'";
    if ($conn->query($delete_sql)) {
        $success = "Report deleted successfully!";
    } else {
        $error = "Error deleting report: " . $conn->error;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$where_conditions = [];

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

$where_sql = "";
if (count($where_conditions) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get all reports with details
$reports = $conn->query("
    SELECT r.*, 
           u.name as citizen_name, 
           u.email as citizen_email,
           u.phone as citizen_phone,
           w.name as worker_name,
           w.id as worker_id
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    LEFT JOIN users w ON r.assigned_to = w.id 
    $where_sql
    ORDER BY 
        CASE r.priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        r.created_at DESC
");

// Get all workers for assignment
$workers = $conn->query("SELECT id, name, email FROM users WHERE type='worker' ORDER BY name");

// Get statistics for filter counts
$total_reports = $conn->query("SELECT COUNT(*) as total FROM reports")->fetch_assoc()['total'];
$pending_count = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='pending'")->fetch_assoc()['total'];
$in_progress_count = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='in_progress'")->fetch_assoc()['total'];
$completed_count = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='completed'")->fetch_assoc()['total'];

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM reports ORDER BY category");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Admin Reports</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - Green Gradient */
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
        
        .admin-info div {
            text-align: right;
        }
        
        .admin-info strong {
            font-size: 15px;
            color: #1e2b3a;
            display: block;
        }
        
        .admin-info .text-muted {
            font-size: 12px;
            color: #7f8c8d !important;
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
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
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
            border-left: 6px solid;
            width: 100%;
            height: 130px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.all { border-left-color: #2c3e50; }
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.progress { border-left-color: #3498db; }
        .stat-card.completed { border-left-color: #27ae60; }
        
        .stat-info h4 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-card.all .stat-info h4 { color: #2c3e50; }
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
        
        .stat-card.all .stat-icon { background: #2c3e50; color: white; }
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
            font-size: 14px;
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
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            color: white;
            width: 100%;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid #95a5a6;
            color: #7f8c8d;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            background: white;
        }
        
        .btn-outline-secondary:hover {
            background: #95a5a6;
            color: white;
        }
        
        /* Reports Section */
        .reports-section {
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
        
        .priority-high { color: #dc3545; font-weight: 600; }
        .priority-medium { color: #f57c00; font-weight: 600; }
        .priority-low { color: #28a745; font-weight: 600; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            white-space: nowrap;
            font-weight: 500;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-action i {
            margin-right: 3px;
            font-size: 10px;
        }
        
        .btn-assign {
            background: #007bff;
            color: white;
        }
        
        .btn-assign:hover {
            background: #0069d9;
            transform: translateY(-1px);
        }
        
        .btn-status {
            background: #28a745;
            color: white;
        }
        
        .btn-status:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
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
        
        .modal-content {
            border-radius: 10px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
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
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                text-align: center;
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
            <a href="admin_reports.php" class="nav-link active">
                <i class="fas fa-list"></i>
                <span>All Reports</span>
            </a>
            <a href="admin_users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="admin_workers.php" class="nav-link">
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
            <h4 class="mb-0"><i class="fas fa-list"></i> All Reports</h4>
            <div class="admin-info">
                <div>
                    <strong><?php echo htmlspecialchars($admin_username); ?></strong>
                    <div class="text-muted">Administrator</div>
                </div>
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card all" onclick="filterByStatus('all')">
                <div class="stat-info">
                    <h4><?php echo $total_reports; ?></h4>
                    <p>Total Reports</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
            
            <div class="stat-card pending" onclick="filterByStatus('pending')">
                <div class="stat-info">
                    <h4><?php echo $pending_count; ?></h4>
                    <p>Pending</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card progress" onclick="filterByStatus('in_progress')">
                <div class="stat-info">
                    <h4><?php echo $in_progress_count; ?></h4>
                    <p>In Progress</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card completed" onclick="filterByStatus('completed')">
                <div class="stat-info">
                    <h4><?php echo $completed_count; ?></h4>
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
                                   placeholder="Search reports..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="admin_reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
       <!-- Reports Table -->
<div class="reports-section">
    <div class="section-header">
        <h5><i class="fas fa-list"></i> Recent Reports</h5>
        <span class="badge">
            <?php echo $reports ? $reports->num_rows : 0; ?> reports found
        </span>
    </div>
    
    <div class="table-container">
        <table id="reportsTable" class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Citizen</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Worker</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports && $reports->num_rows > 0): ?>
                    <?php while($report = $reports->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $report['id']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($report['title']); ?></strong></td>
                            <td>
                                <div class="citizen-info">
                                    <span class="citizen-name"><?php echo htmlspecialchars($report['citizen_name'] ?? 'Unknown'); ?></span>
                                    <span class="citizen-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($report['citizen_phone'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td><span class="badge" style="background: #e9ecef;"><?php echo ucfirst($report['category']); ?></span></td>
                            <td><?php echo htmlspecialchars(substr($report['location'], 0, 25)); ?>...</td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $report['status'] == 'pending' ? 'pending' : 
                                        ($report['status'] == 'in_progress' ? 'progress' : 'completed'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-<?php echo $report['priority']; ?>">
                                    <i class="fas fa-flag"></i> <?php echo ucfirst($report['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($report['worker_name']): ?>
                                    <span class="badge bg-info text-white"><?php echo htmlspecialchars($report['worker_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($report['status'] == 'pending'): ?>
                                        <button class="btn-action btn-assign" onclick="showAssignModal(<?php echo $report['id']; ?>)">
                                            <i class="fas fa-user-plus"></i> Assign
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action btn-status" onclick="showStatusModal(<?php echo $report['id']; ?>, '<?php echo $report['status']; ?>')">
                                        <i class="fas fa-edit"></i> Status
                                    </button>
                                    <button class="btn-action btn-view" onclick="viewReport(<?php echo $report['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h5>No Reports Found</h5>
                                <p class="text-muted">Try adjusting your filters or search criteria</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
    <!-- Assign Worker Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus"></i> Assign Worker
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="assignReportId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Worker</label>
                            <select class="form-select" name="worker_id" required>
                                <option value="">Choose a worker...</option>
                                <?php 
                                if ($workers && $workers->num_rows > 0) {
                                    $workers->data_seek(0);
                                    while($worker = $workers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $worker['id']; ?>">
                                        <?php echo htmlspecialchars($worker['name']); ?> 
                                        (<?php echo htmlspecialchars($worker['email']); ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                } else {
                                ?>
                                    <option value="" disabled>No workers available</option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Assigning a worker will change the report status to "In Progress"
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_worker" class="btn btn-primary">
                            <i class="fas fa-user-check"></i> Assign Worker
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> Update Status
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="statusReportId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Status</label>
                            <select class="form-select" name="status" id="statusSelect" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning" id="statusAlert">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Changing to "Completed" will award 25 points to the citizen
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#reportsTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search reports..."
                }
            });
        });
        
        function showAssignModal(reportId) {
            document.getElementById('assignReportId').value = reportId;
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }
        
        function showStatusModal(reportId, currentStatus) {
            document.getElementById('statusReportId').value = reportId;
            document.getElementById('statusSelect').value = currentStatus;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        function viewReport(reportId) {
            alert('Viewing report #' + reportId);
        }
        
        function filterByStatus(status) {
            const form = document.getElementById('filterForm');
            const statusSelect = form.querySelector('select[name="status"]');
            statusSelect.value = status;
            form.submit();
        }
        
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