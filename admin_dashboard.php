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

// Get admin details
$admin_query = "SELECT * FROM admin WHERE username='$admin_username'";
$admin_result = $conn->query($admin_query);
$admin = $admin_result->fetch_assoc();

// Handle worker assignment
if (isset($_POST['assign_worker'])) {
    $report_id = $conn->real_escape_string($_POST['report_id']);
    $worker_id = $conn->real_escape_string($_POST['worker_id']);
    
    $update_sql = "UPDATE reports SET assigned_to = '$worker_id', status = 'in_progress' WHERE id = '$report_id'";
    if ($conn->query($update_sql)) {
        $success = "Worker assigned successfully!";
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
        if ($status == 'completed') {
            // Add rewards points to citizen
            $report_query = $conn->query("SELECT user_id FROM reports WHERE id='$report_id'");
            $report = $report_query->fetch_assoc();
            $conn->query("UPDATE users SET rewards_points = rewards_points + 25 WHERE id='{$report['user_id']}'");
        }
        $success = "Status updated successfully!";
    } else {
        $error = "Error updating status: " . $conn->error;
    }
}

// Get statistics
$total_reports = $conn->query("SELECT COUNT(*) as total FROM reports")->fetch_assoc()['total'];
$pending_reports = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='pending'")->fetch_assoc()['total'];
$in_progress_reports = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='in_progress'")->fetch_assoc()['total'];
$completed_reports = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='completed'")->fetch_assoc()['total'];
$total_citizens = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='citizen'")->fetch_assoc()['total'];
$total_workers = $conn->query("SELECT COUNT(*) as total FROM users WHERE type='worker'")->fetch_assoc()['total'];

// ============================================
// GET WEEKLY ACTIVITY DATA FROM DATABASE
// Monday to Sunday order
// ============================================
$weekly_data = [];
$weekly_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Get Monday of current week
$monday = date('Y-m-d', strtotime('monday this week'));

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($monday . " +$i days"));
    $query = "SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) = '$date'";
    $result = $conn->query($query);
    $count = ($result) ? $result->fetch_assoc()['count'] : 0;
    $weekly_data[] = $count;
}

// Get all reports with details
$reports = $conn->query("
    SELECT r.*, 
           u.name as citizen_name, 
           u.email as citizen_email,
           w.name as worker_name 
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    LEFT JOIN users w ON r.assigned_to = w.id 
    ORDER BY r.created_at DESC 
    LIMIT 20
");

// Get all workers for assignment
$workers = $conn->query("SELECT id, name, email FROM users WHERE type='worker'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-info h3 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.total { background: #e3f2fd; color: #1976d2; }
        .stat-icon.pending { background: #fff3e0; color: #f57c00; }
        .stat-icon.progress { background: #e8f5e9; color: #388e3c; }
        .stat-icon.completed { background: #e8eaf6; color: #3f51b5; }
        .stat-icon.citizens { background: #f3e5f5; color: #7b1fa2; }
        .stat-icon.workers { background: #e1f5fe; color: #0288d1; }
        
        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-card h5 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        /* Reports Table */
        .reports-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h4 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-header i {
            margin-right: 10px;
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
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
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
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
        }
        
        /* Modals */
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
        
        /* Alert Messages */
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
            }
            
            .nav-link i {
                width: auto;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .charts-row {
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
            <a href="admin_dashboard.php" class="nav-link active">
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
            <h4 class="mb-0"><i class="fas fa-tachometer-alt"></i> Dashboard</h4>
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
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_reports; ?></h3>
                    <p>Total Reports</p>
                </div>
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $pending_reports; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $in_progress_reports; ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="stat-icon progress">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $completed_reports; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_citizens; ?></h3>
                    <p>Citizens</p>
                </div>
                <div class="stat-icon citizens">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_workers; ?></h3>
                    <p>Workers</p>
                </div>
                <div class="stat-icon workers">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <h5><i class="fas fa-chart-pie"></i> Reports by Status</h5>
                <canvas id="statusChart" style="max-height: 300px;"></canvas>
            </div>
            
            <div class="chart-card">
                <h5><i class="fas fa-chart-line"></i> Weekly Activity</h5>
                <canvas id="weeklyChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <!-- Recent Reports -->
        <div class="reports-section">
            <div class="section-header">
                <h4><i class="fas fa-history"></i> Recent Reports</h4>
                <a href="admin_reports.php" class="btn btn-light btn-sm">View All</a>
            </div>
            
            <div class="table-container">
                <table>
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
                        <?php if ($reports->num_rows > 0): ?>
                            <?php while($report = $reports->fetch_assoc()): ?>
                                <tr>
                                    <td><small>#<?php echo $report['id']; ?></small></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($report['title'], 0, 30)); ?>...</strong>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($report['citizen_name']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #e9ecef;">
                                            <?php echo ucfirst($report['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($report['location'], 0, 20)); ?>...</small>
                                    </td>
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
                                            <?php echo ucfirst($report['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($report['worker_name']): ?>
                                            <small><?php echo htmlspecialchars($report['worker_name']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Not assigned</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($report['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-primary btn-action" 
                                                        onclick="showAssignModal(<?php echo $report['id']; ?>)">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-success btn-action" 
                                                    onclick="showStatusModal(<?php echo $report['id']; ?>, '<?php echo $report['status']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-info btn-action" 
                                                    onclick="viewReport(<?php echo $report['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No reports found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                            <label class="form-label">Select Worker</label>
                            <select class="form-select" name="worker_id" required>
                                <option value="">Choose worker...</option>
                                <?php 
                                $workers->data_seek(0);
                                while($worker = $workers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $worker['id']; ?>">
                                        <?php echo htmlspecialchars($worker['name']); ?> 
                                        (<?php echo htmlspecialchars($worker['email']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_worker" class="btn btn-primary">
                            Assign Worker
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
                            <label class="form-label">Select Status</label>
                            <select class="form-select" name="status" id="statusSelect" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js Scripts -->
    <script>
        // Status Chart
        const ctx1 = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [<?php echo $pending_reports; ?>, <?php echo $in_progress_reports; ?>, <?php echo $completed_reports; ?>],
                    backgroundColor: ['#f57c00', '#1976d2', '#388e3c'],
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
        
        // Weekly Chart - Data from Database (Monday to Sunday)
        const ctx2 = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Reports',
                    data: [<?php echo implode(',', $weekly_data); ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
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
        
        // Modal functions
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
            // In a real application, this would open a detailed view
            alert('View report details for ID: ' + reportId);
        }
        
        // Auto-hide alerts after 5 seconds
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