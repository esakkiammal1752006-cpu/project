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

// Initialize variables
$reports = [];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$sql_where = "WHERE user_id = '$user_id'";

// IMPORTANT: Use the status directly from the database (which is 'completed')
if ($status_filter != 'all') {
    $sql_where .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($category_filter != 'all') {
    $sql_where .= " AND category = '" . $conn->real_escape_string($category_filter) . "'";
}

if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $sql_where .= " AND (title LIKE '%$search%' 
                      OR description LIKE '%$search%'
                      OR report_id LIKE '%$search%')";
}

// Fetch user's reports
$sql = "SELECT * FROM reports $sql_where ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get counts for each status - IMPORTANT: Use 'completed' not 'resolved'
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0
];

$count_sql = "SELECT status, COUNT(*) as count FROM reports WHERE user_id = '$user_id' GROUP BY status";
$count_result = $conn->query($count_sql);
$total_reports = 0;

if ($count_result->num_rows > 0) {
    while($row = $count_result->fetch_assoc()) {
        // Direct mapping - use the status as it is from database
        $status_counts[$row['status']] = $row['count'];
        $total_reports += $row['count'];
    }
}
$status_counts['all'] = $total_reports;

// Get unique categories for filter
$categories = [];
$cat_sql = "SELECT DISTINCT category FROM reports WHERE user_id = '$user_id' ORDER BY category";
$cat_result = $conn->query($cat_sql);
if ($cat_result->num_rows > 0) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Debug - Remove after testing (uncomment to see what's happening)
// echo "<!-- DEBUG: Status filter: $status_filter, SQL: $sql -->";
// echo "<!-- DEBUG: Number of reports found: " . count($reports) . " -->";

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - My Reports</title>
    
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
        
        /* Reports Container */
        .reports-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .reports-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
        }
        
        .reports-header::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .reports-header h2 {
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .reports-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .reports-body {
            padding: 25px;
        }
        
        /* Status Cards - EXACT SAME AS WORKER DASHBOARD */
        .status-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .status-card {
            background: white;
            border-radius: 16px;
            padding: 20px 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 6px solid;
            cursor: pointer;
            width: 100%;
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            text-decoration: none;
        }
        
        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .status-card.all { border-left-color: #2c3e50; }
        .status-card.pending { border-left-color: #f39c12; }
        .status-card.in_progress { border-left-color: #3498db; }
        .status-card.completed { border-left-color: #27ae60; }
        .status-card.rejected { border-left-color: #dc3545; }
        
        .status-count {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .status-card.all .status-count { color: #2c3e50; }
        .status-card.pending .status-count { color: #e67e22; }
        .status-card.in_progress .status-count { color: #2980b9; }
        .status-card.completed .status-count { color: #27ae60; }
        .status-card.rejected .status-count { color: #dc3545; }
        
        .status-card div:last-child {
            font-size: 14px;
            font-weight: 600;
            color: #7f8c8d;
        }
        
        .status-card.active {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        /* Filters - Matching worker dashboard */
        .filters-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-group {
            margin-bottom: 15px;
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
            color: #7f8c8d;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            color: white;
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
            background: white;
        }
        
        .btn-outline-secondary:hover {
            background: #95a5a6;
            color: white;
        }
        
        /* Report Cards - Matching worker dashboard */
        .report-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }
        
        .report-card:hover {
            border-color: #28a745;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.1);
            transform: translateY(-3px);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .report-id {
            font-family: monospace;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .report-date {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .report-description {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .report-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .detail-item i {
            color: #28a745;
        }
        
        .category-badge {
            background: #e9ecef;
            color: #2c3e50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .report-image {
            max-width: 150px;
            max-height: 100px;
            border-radius: 8px;
            object-fit: cover;
            margin-top: 10px;
        }
        
        .no-reports {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
        
        .no-reports i {
            font-size: 48px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .no-reports h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .view-details {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }
        
        .view-details:hover {
            color: #218838;
            text-decoration: underline;
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
        
        /* Text Muted */
        .text-muted {
            color: #7f8c8d !important;
        }
        
        /* Responsive - EXACT SAME AS WORKER DASHBOARD */
        @media (max-width: 1200px) {
            .status-cards {
                grid-template-columns: repeat(3, 1fr);
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
            
            .status-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .status-card {
                height: 100px;
                padding: 15px 10px;
            }
            
            .status-count {
                font-size: 24px;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .status-cards {
                grid-template-columns: 1fr;
            }
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
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>
                <span>Report Issue</span>
            </a>
            <a href="my_reports.php" class="nav-link active">
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
            <h1><i class="fas fa-list"></i> My Reports</h1>
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
        
        <!-- Status Overview Cards - EXACT SAME AS WORKER DASHBOARD -->
        <div class="status-cards">
            <a href="?status=all" class="text-decoration-none">
                <div class="status-card all <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <div class="status-count"><?php echo $status_counts['all']; ?></div>
                    <div>Total Reports</div>
                </div>
            </a>
            
            <a href="?status=pending" class="text-decoration-none">
                <div class="status-card pending <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    <div class="status-count"><?php echo $status_counts['pending']; ?></div>
                    <div>Pending</div>
                </div>
            </a>
            
            <a href="?status=in_progress" class="text-decoration-none">
                <div class="status-card in_progress <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">
                    <div class="status-count"><?php echo $status_counts['in_progress']; ?></div>
                    <div>In Progress</div>
                </div>
            </a>
            
            <a href="?status=completed" class="text-decoration-none">
                <div class="status-card completed <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                    <div class="status-count"><?php echo $status_counts['completed']; ?></div>
                    <div>Completed</div>
                </div>
            </a>
            
            <a href="?status=rejected" class="text-decoration-none">
                <div class="status-card rejected <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                    <div class="status-count"><?php echo $status_counts['rejected']; ?></div>
                    <div>Rejected</div>
                </div>
            </a>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(htmlspecialchars($category)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <div class="search-box">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by title, description, or ID" 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        Showing <?php echo count($reports); ?> report(s)
                    </div>
                    <div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="my_reports.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Reports List -->
        <div class="reports-container">
            <div class="reports-header">
                <h2><i class="fas fa-file-alt"></i> My Submitted Reports</h2>
                <p>Track the status of your reports and get updates</p>
            </div>
            
            <div class="reports-body">
                <?php if (count($reports) > 0): ?>
                    <?php foreach($reports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div>
                                    <span class="report-id">
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($report['report_id']); ?>
                                    </span>
                                    <span class="report-date ms-3">
                                        <i class="far fa-calendar"></i> 
                                        <?php echo date('F j, Y', strtotime($report['created_at'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <?php 
                                    $status_class = '';
                                    switch($report['status']) {
                                        case 'pending': $status_class = 'status-pending'; break;
                                        case 'in_progress': $status_class = 'status-in_progress'; break;
                                        case 'completed': $status_class = 'status-completed'; break;
                                        case 'rejected': $status_class = 'status-rejected'; break;
                                        default: $status_class = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h3 class="report-title"><?php echo htmlspecialchars($report['title']); ?></h3>
                            
                            <p class="report-description">
                                <?php echo htmlspecialchars(substr($report['description'], 0, 200)); ?>
                                <?php if (strlen($report['description']) > 200): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="report-details">
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span class="category-badge">
                                        <?php echo ucfirst(htmlspecialchars($report['category'])); ?>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-flag"></i>
                                    <?php 
                                    $priority_class = '';
                                    switch($report['priority']) {
                                        case 'low': $priority_class = 'priority-low'; break;
                                        case 'medium': $priority_class = 'priority-medium'; break;
                                        case 'high': $priority_class = 'priority-high'; break;
                                        default: $priority_class = 'priority-medium';
                                    }
                                    ?>
                                    <span class="priority-badge <?php echo $priority_class; ?>">
                                        <?php echo ucfirst(htmlspecialchars($report['priority'])); ?> Priority
                                    </span>
                                </div>
                                
                                <?php if (!empty($report['image'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-image"></i>
                                        <a href="uploads/<?php echo htmlspecialchars($report['image']); ?>" 
                                           target="_blank" class="view-details">
                                            View Photo
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($report['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($report['image']); ?>" 
                                     class="report-image" 
                                     alt="Report Image"
                                     onclick="openImageModal('<?php echo htmlspecialchars($report['image']); ?>')">
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-muted">
                                    <small>
                                        Last updated: 
                                        <?php 
                                        $updated_at = !empty($report['updated_at']) ? $report['updated_at'] : $report['created_at'];
                                        echo date('M d, Y H:i', strtotime($updated_at)); 
                                        ?>
                                    </small>
                                </div>
                                <a href="#" class="view-details" onclick="viewReportDetails('<?php echo $report['report_id']; ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-reports">
                        <i class="fas fa-file-alt"></i>
                        <h3>No reports found</h3>
                        <p class="mb-3">
                            <?php if ($status_filter != 'all' || $category_filter != 'all' || !empty($search_query)): ?>
                                Try changing your filters or search criteria
                            <?php else: ?>
                                You haven't submitted any reports yet
                            <?php endif; ?>
                        </p>
                        <a href="report.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus-circle"></i> Submit Your First Report
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #28a745); color: white;">
                    <h5 class="modal-title">Report Image</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Full Size Image" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        function openImageModal(imagePath) {
            const modalImage = document.getElementById('modalImage');
            modalImage.src = 'uploads/' + imagePath;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        function viewReportDetails(reportId) {
            alert('Report details for: ' + reportId);
        }
        
        // Auto-submit search on Enter
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
        
        // Update status cards with active class
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    document.querySelectorAll('.status-card').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>