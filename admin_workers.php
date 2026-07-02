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

// Handle worker deletion
if (isset($_GET['delete'])) {
    $worker_id = $conn->real_escape_string($_GET['delete']);
    
    // Check if worker has any assigned reports
    $check_reports = $conn->query("SELECT id FROM reports WHERE assigned_to = '$worker_id'");
    if ($check_reports->num_rows > 0) {
        $error = "Cannot delete worker. They have assigned reports!";
    } else {
        $delete_sql = "DELETE FROM users WHERE id = '$worker_id' AND type = 'worker'";
        if ($conn->query($delete_sql)) {
            $success = "Worker deleted successfully!";
        } else {
            $error = "Error deleting worker: " . $conn->error;
        }
    }
}

// Handle worker status update
if (isset($_POST['update_status'])) {
    $worker_id = $conn->real_escape_string($_POST['worker_id']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Add status column if not exists
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active'");
    
    $update_sql = "UPDATE users SET status = '$status' WHERE id = '$worker_id'";
    if ($conn->query($update_sql)) {
        $success = "Worker status updated successfully!";
    } else {
        $error = "Error updating status: " . $conn->error;
    }
}

// Handle add new worker
if (isset($_POST['add_worker'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Generate unique user ID
    $user_id = "WRK" . date('Ymd') . rand(1000, 9999);
    
    // Check if email already exists
    $check_email = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check_email->num_rows > 0) {
        $error = "Email already registered!";
    } else {
        $sql = "INSERT INTO users (user_id, name, email, phone, address, password, type, status, created_at) 
                VALUES ('$user_id', '$name', '$email', '$phone', '$address', '$password', 'worker', 'active', NOW())";
        
        if ($conn->query($sql)) {
            $success = "Worker added successfully!";
        } else {
            $error = "Error adding worker: " . $conn->error;
        }
    }
}

// Handle edit worker
if (isset($_POST['edit_worker'])) {
    $worker_id = $conn->real_escape_string($_POST['worker_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    $update_sql = "UPDATE users SET 
                   name = '$name', 
                   email = '$email', 
                   phone = '$phone', 
                   address = '$address'
                   WHERE id = '$worker_id' AND type = 'worker'";
    
    if ($conn->query($update_sql)) {
        $success = "Worker updated successfully!";
    } else {
        $error = "Error updating worker: " . $conn->error;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$where_conditions = ["type = 'worker'"];

if ($status_filter != 'all') {
    $where_conditions[] = "status = '" . $conn->real_escape_string($status_filter) . "'";
}
if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR user_id LIKE '%$search%')";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get all workers
$workers = $conn->query("SELECT * FROM users $where_sql ORDER BY created_at DESC");

// Get worker statistics
$total_workers = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='worker'")->fetch_assoc()['total'];
$active_workers = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='worker' AND (status = 'active' OR status IS NULL)")->fetch_assoc()['total'];
$inactive_workers = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='worker' AND status = 'inactive'")->fetch_assoc()['total'];

// Get workers with assigned reports count
$workers_with_counts = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM reports WHERE assigned_to = u.id) as assigned_count,
           (SELECT COUNT(*) FROM reports WHERE assigned_to = u.id AND status = 'completed') as completed_count,
           (SELECT COUNT(*) FROM reports WHERE assigned_to = u.id AND status = 'in_progress') as in_progress_count
    FROM users u
    WHERE u.type = 'worker'
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Manage Workers</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS - Matching admin_reports.php theme -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - Exact same as admin_reports */
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
        
        /* Top Bar - Exact same as admin_reports */
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
        
        /* Stats Cards - Same size as admin_reports */
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
            cursor: pointer;
            width: 100%;
            height: 130px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: #2c3e50; }
        .stat-card.active { border-left-color: #27ae60; }
        .stat-card.inactive { border-left-color: #e74c3c; }
        
        .stat-info h4 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-card.total .stat-info h4 { color: #2c3e50; }
        .stat-card.active .stat-info h4 { color: #27ae60; }
        .stat-card.inactive .stat-info h4 { color: #e74c3c; }
        
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
        .stat-card.active .stat-icon { background: #27ae60; color: white; }
        .stat-card.inactive .stat-icon { background: #e74c3c; color: white; }
        
        /* Add Worker Button */
        .add-button-container {
            margin-bottom: 20px;
            text-align: right;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        /* Filters Section - Exact same as admin_reports */
        .filters-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        /* Workers Table Section - Exact same as reports section */
        .workers-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
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
        
        /* Worker Performance Badges */
        .performance-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin: 2px;
        }
        
        .performance-assigned {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .performance-progress {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .performance-completed {
            background: #d4edda;
            color: #155724;
        }
        
        /* Status Badges - Same as admin_users */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Action Buttons - Exact same as admin_reports */
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
        
        .btn-edit {
            background: #007bff;
        }
        
        .btn-edit:hover {
            background: #0069d9;
        }
        
        .btn-status {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-status:hover {
            background: #e0a800;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-view {
            background: #17a2b8;
        }
        
        .btn-view:hover {
            background: #138496;
        }
        
        /* Worker Info */
        .worker-id {
            font-family: monospace;
            font-size: 11px;
            color: #6c757d;
        }
        
        .worker-contact {
            font-size: 11px;
            color: #6c757d;
        }
        
        .worker-contact i {
            width: 14px;
            color: #28a745;
        }
        
        /* Modals - Exact same as admin_reports */
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
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
        }
        
        /* Alert Messages - Exact same as admin_reports */
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Responsive - Same as admin_reports */
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
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - Same as admin_reports -->
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
        <!-- Top Bar - Same as admin_reports -->
        <div class="top-bar">
            <h4 class="mb-0"><i class="fas fa-tools"></i> Manage Workers</h4>
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
        
        <!-- Statistics Cards - Same style as admin_reports -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-info">
                    <h4><?php echo $total_workers; ?></h4>
                    <p>Total Workers</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-info">
                    <h4><?php echo $active_workers; ?></h4>
                    <p>Active Workers</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card inactive">
                <div class="stat-info">
                    <h4><?php echo $inactive_workers; ?></h4>
                    <p>Inactive Workers</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
        
        <!-- Add Worker Button -->
        <div class="add-button-container">
            <button class="btn-add" onclick="showAddWorkerModal()">
                <i class="fas fa-plus-circle"></i> Add New Worker
            </button>
        </div>
        
        <!-- Filters - Same as admin_reports -->
        <div class="filters-section">
            <form method="GET" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by name, email, phone..." 
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
                        <a href="admin_workers.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Workers Table - Same style as reports table -->
        <div class="workers-section">
            <div class="section-header">
                <h5><i class="fas fa-list"></i> Municipal Workers</h5>
                <span class="badge">
                    <?php echo $workers ? $workers->num_rows : 0; ?> workers found
                </span>
            </div>
            
            <div class="table-container">
                <table id="workersTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Worker ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Performance</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($workers_with_counts && $workers_with_counts->num_rows > 0): ?>
                            <?php while($worker = $workers_with_counts->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $worker['id']; ?></strong></td>
                                    <td>
                                        <span class="worker-id"><?php echo htmlspecialchars($worker['user_id']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($worker['name']); ?></strong>
                                        <?php if(!empty($worker['address'])): ?>
                                            <div class="worker-contact">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?php echo htmlspecialchars(substr($worker['address'], 0, 30)); ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="worker-contact">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($worker['email']); ?>
                                        </div>
                                        <div class="worker-contact">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($worker['phone']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="performance-badge performance-assigned">
                                            <i class="fas fa-tasks"></i> <?php echo $worker['assigned_count']; ?> Assigned
                                        </span>
                                        <span class="performance-badge performance-progress">
                                            <i class="fas fa-spinner"></i> <?php echo $worker['in_progress_count']; ?> In Progress
                                        </span>
                                        <span class="performance-badge performance-completed">
                                            <i class="fas fa-check-circle"></i> <?php echo $worker['completed_count']; ?> Completed
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = isset($worker['status']) ? $worker['status'] : 'active';
                                        ?>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($worker['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewWorkerTasks(<?php echo $worker['id']; ?>)"
                                                    title="View Tasks">
                                                <i class="fas fa-eye"></i> Tasks
                                            </button>
                                            
                                            <button class="btn-action btn-edit" 
                                                    onclick="editWorker(<?php echo $worker['id']; ?>)"
                                                    title="Edit Worker">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            
                                            <?php 
                                            $current_status = isset($worker['status']) ? $worker['status'] : 'active';
                                            $new_status = $current_status == 'active' ? 'inactive' : 'active';
                                            $status_text = $current_status == 'active' ? 'Deactivate' : 'Activate';
                                            ?>
                                            <button class="btn-action btn-status" 
                                                    onclick="updateStatus(<?php echo $worker['id']; ?>, '<?php echo $new_status; ?>')"
                                                    title="<?php echo $status_text; ?> Worker">
                                                <i class="fas fa-power-off"></i> <?php echo $status_text; ?>
                                            </button>
                                            
                                            <button class="btn-action btn-delete" 
                                                    onclick="confirmDelete(<?php echo $worker['id']; ?>)"
                                                    title="Delete Worker">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                    <h5>No Workers Found</h5>
                                    <p class="text-muted">Click "Add New Worker" to register municipal workers</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Worker Modal -->
    <div class="modal fade" id="addWorkerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus"></i> Add New Worker
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_worker" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Worker
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Worker Modal -->
    <div class="modal fade" id="editWorkerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> Edit Worker
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="worker_id" id="editWorkerId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" class="form-control" name="name" id="editWorkerName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" name="email" id="editWorkerEmail" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editWorkerPhone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <textarea class="form-control" name="address" id="editWorkerAddress" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_worker" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Worker
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-power-off"></i> Update Status
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="worker_id" id="statusWorkerId">
                        <input type="hidden" name="status" id="statusValue">
                        
                        <p id="statusMessage">Are you sure you want to update this worker's status?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this worker?</p>
                    <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                    <p class="text-warning">Note: Workers with assigned reports cannot be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Worker
                    </a>
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
        // Initialize DataTable - Same as admin_reports
        $(document).ready(function() {
            $('#workersTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search workers..."
                }
            });
        });
        
        // Worker data for edit modal
        const workers = <?php 
            $workers_with_counts->data_seek(0);
            $worker_data = [];
            while($row = $workers_with_counts->fetch_assoc()) {
                $worker_data[$row['id']] = $row;
            }
            echo json_encode($worker_data);
        ?>;
        
        function showAddWorkerModal() {
            new bootstrap.Modal(document.getElementById('addWorkerModal')).show();
        }
        
        function editWorker(workerId) {
            const worker = workers[workerId];
            if (worker) {
                document.getElementById('editWorkerId').value = workerId;
                document.getElementById('editWorkerName').value = worker.name || '';
                document.getElementById('editWorkerEmail').value = worker.email || '';
                document.getElementById('editWorkerPhone').value = worker.phone || '';
                document.getElementById('editWorkerAddress').value = worker.address || '';
                
                new bootstrap.Modal(document.getElementById('editWorkerModal')).show();
            }
        }
        
        function viewWorkerTasks(workerId) {
            window.location.href = 'admin_worker_tasks.php?worker_id=' + workerId;
        }
        
        function updateStatus(workerId, status) {
            document.getElementById('statusWorkerId').value = workerId;
            document.getElementById('statusValue').value = status;
            
            const message = status === 'active' 
                ? 'Are you sure you want to activate this worker?' 
                : 'Are you sure you want to deactivate this worker?';
            document.getElementById('statusMessage').innerText = message;
            
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        function confirmDelete(workerId) {
            document.getElementById('confirmDeleteBtn').href = 'admin_workers.php?delete=' + workerId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
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