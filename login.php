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

// Initialize variables
$error = "";
$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $user_type = $conn->real_escape_string($_POST['user_type']);
    
    // Validate input
    if (empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required!";
    } else {
        // Check user credentials (only citizen and worker types)
        $sql = "SELECT * FROM users WHERE email='$email' AND type='$user_type'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type'];
                $_SESSION['user_unique_id'] = $user['user_id'];
                
                // Redirect based on user type
                if ($user['type'] == 'citizen') {
                    header("Location: dashboard.php");
                    exit();
                } elseif ($user['type'] == 'worker') {
                    header("Location: worker.php");
                    exit();
                }
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "No account found with this email and user type!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Moving Background */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .moving-bg {
            position: absolute;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                rgba(52, 152, 219, 0.05) 0%,
                rgba(46, 204, 113, 0.05) 25%,
                rgba(155, 89, 182, 0.05) 50%,
                rgba(241, 196, 15, 0.05) 75%,
                rgba(230, 126, 34, 0.05) 100%
            );
            animation: moveBackground 20s linear infinite;
        }
        
        @keyframes moveBackground {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
        
        .bg-particles {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .particle {
            position: absolute;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 50%;
            animation: floatParticle 15s infinite linear;
        }
        
        @keyframes floatParticle {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shineHeader 3s infinite;
        }
        
        @keyframes shineHeader {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
            transform: translateY(-2px);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        
        .btn-success:hover::after {
            left: 140%;
        }
        
        .user-type-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .user-type-option {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .user-type-option:hover {
            border-color: #28a745;
            transform: translateY(-2px);
        }
        
        .user-type-option.selected {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .user-type-option i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        
        .register-link a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .register-link a:hover {
            color: #218838;
            text-decoration: underline;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            animation: slideDown 0.5s;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .navbar {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 5px;
        }
        
        .forgot-password a {
            color: #28a745;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .forgot-password a:hover {
            color: #218838;
            text-decoration: underline;
        }
        
        .home-link {
            text-align: center;
            margin-top: 15px;
            color: #6c757d;
        }
        
        .home-link a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .home-link a:hover {
            color: #218838;
            text-decoration: underline;
        }
        
        .input-group {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Moving Background -->
    <div class="background-container">
        <div class="moving-bg"></div>
        <div class="bg-particles" id="particles"></div>
    </div>
    
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2 class="mb-3"><i class="fas fa-sign-in-alt"></i> Welcome Back</h2>
                <p class="mb-0">Login to continue your Swachh Bharat journey</p>
            </div>
            
            <div class="login-body">
                <?php if($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" 
                                   id="password" placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                        <div class="forgot-password">
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Login As *</label>
                        <div class="user-type-options">
                            <div class="user-type-option" data-type="citizen">
                                <i class="fas fa-user"></i>
                                <div>Citizen</div>
                            </div>
                            <div class="user-type-option" data-type="worker">
                                <i class="fas fa-tools"></i>
                                <div>Worker</div>
                            </div>
                        </div>
                        <input type="hidden" name="user_type" id="userType" value="" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success mb-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Create one here</a>
                    </div>
                    
                    <div class="home-link">
                        <a href="index.php">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create floating particles
            const particlesContainer = document.getElementById('particles');
            const colors = ['rgba(52, 152, 219, 0.1)', 'rgba(46, 204, 113, 0.1)', 'rgba(155, 89, 182, 0.1)', 'rgba(241, 196, 15, 0.1)', 'rgba(230, 126, 34, 0.1)'];
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 60 + 10;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                
                const duration = Math.random() * 10 + 10;
                particle.style.animationDuration = `${duration}s`;
                
                const delay = Math.random() * 5;
                particle.style.animationDelay = `${delay}s`;
                
                particlesContainer.appendChild(particle);
            }
            
            // User type selection
            const userTypeOptions = document.querySelectorAll('.user-type-option');
            const userTypeInput = document.getElementById('userType');
            
            userTypeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    userTypeOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set hidden input value
                    userTypeInput.value = this.getAttribute('data-type');
                });
            });
            
            // Auto-select citizen by default
            document.querySelector('.user-type-option[data-type="citizen"]').click();
            
            // Form validation
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                if (!userTypeInput.value) {
                    e.preventDefault();
                    alert('Please select how you want to login');
                    return false;
                }
                return true;
            });
        });
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>