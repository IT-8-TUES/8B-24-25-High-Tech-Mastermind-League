document.getElementById('register-form').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const errorMessage = document.getElementById('error-message');
    
    errorMessage.style.display = 'none';
    
    if (username === '' || email === '' || password === '' || confirmPassword === '') {
        e.preventDefault();
        errorMessage.textContent = 'Please fill in all fields';
        errorMessage.style.display = 'block';
        return false;
    }
    
    if (username.length < 3) {
        e.preventDefault();
        errorMessage.textContent = 'Username must be at least 3 characters';
        errorMessage.style.display = 'block';
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        errorMessage.textContent = 'Please enter a valid email address';
        errorMessage.style.display = 'block';
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        errorMessage.textContent = 'Password must be at least 6 characters';
        errorMessage.style.display = 'block';
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        errorMessage.textContent = 'Passwords do not match';
        errorMessage.style.display = 'block';
        return false;
    }
});