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
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $conn->real_escape_string($_POST['user_type']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || empty($user_type)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Check if email already exists
        $check_email = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check_email->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate unique user ID
            $user_id = "USER" . date('Ymd') . rand(1000, 9999);
            
            // Insert into database
            $sql = "INSERT INTO users (user_id, name, email, phone, address, password, type, created_at) 
                    VALUES ('$user_id', '$name', '$email', '$phone', '$address', '$hashed_password', '$user_type', NOW())";
            
            if ($conn->query($sql) === TRUE) {
                $success = "Registration successful! You can now login.";
                // Clear form
                $name = $email = $phone = $address = "";
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Register</title>
    
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
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-card {
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
        
        .register-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
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
        
        .register-body {
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        
        .login-link a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .login-link a:hover {
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
            background: #ffc107;
            width: 66%;
        }
        
        .password-strength.strong .password-strength-bar {
            background: #28a745;
            width: 100%;
        }
        
        .navbar {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
            100% {
                transform: translateY(0px);
            }
        }
        
        .form-group {
            margin-bottom: 20px;
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
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Register Form -->
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h2 class="mb-3"><i class="fas fa-user-plus"></i> Create Account</h2>
                <p class="mb-0">Join Swachh Bharat Mission and help keep our city clean</p>
            </div>
            
            <div class="register-body">
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
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                                   placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" 
                                   placeholder="Enter your phone number" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" name="address" rows="2" 
                                      placeholder="Enter your address"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">User Type *</label>
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
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" 
                                   id="password" placeholder="Enter password" required 
                                   onkeyup="checkPasswordStrength()">
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <div class="password-strength-bar"></div>
                        </div>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="confirm_password" 
                                   id="confirmPassword" placeholder="Confirm password" required>
                        </div>
                        <div id="passwordMatch" class="mt-2"></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success mb-3">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Swachh Bharat Mission - Terms of Service</strong></p>
                    <p>1. You must provide accurate and truthful information during registration.</p>
                    <p>2. Do not submit false reports or spam the system.</p>
                    <p>3. Respect privacy and do not share personal information of others.</p>
                    <p>4. The administration reserves the right to suspend accounts that violate terms.</p>
                    <p>5. By registering, you agree to receive notifications about your reports.</p>
                    <p>6. You are responsible for maintaining the confidentiality of your account.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
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
            
            // Check password match
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const passwordMatchDiv = document.getElementById('passwordMatch');
            
            function checkPasswordMatch() {
                if (passwordInput.value && confirmPasswordInput.value) {
                    if (passwordInput.value === confirmPasswordInput.value) {
                        passwordMatchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
                    } else {
                        passwordMatchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                    }
                } else {
                    passwordMatchDiv.innerHTML = '';
                }
            }
            
            passwordInput.addEventListener('keyup', checkPasswordMatch);
            confirmPasswordInput.addEventListener('keyup', checkPasswordMatch);
            
            // Form validation
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                if (!userTypeInput.value) {
                    e.preventDefault();
                    alert('Please select a user type');
                    return false;
                }
                
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                return true;
            });
        });
        
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
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
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>