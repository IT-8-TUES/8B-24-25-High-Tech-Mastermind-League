document.getElementById('login-form').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('error-message');
    
    if (username === '' || password === '') {
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
    
    if (password.length < 6) {
        e.preventDefault();
        errorMessage.textContent = 'Password must be at least 6 characters';
        errorMessage.style.display = 'block';
        return false;
    }
});

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

window.onload = function() {
    const logoutMessage = getCookie('logout_message');
    if (logoutMessage) {
        const successMessage = document.getElementById('success-message');
        successMessage.textContent = logoutMessage;
        successMessage.style.display = 'block';
        
        document.cookie = 'logout_message=; Max-Age=-99999999;';
        
        setTimeout(function() {
            successMessage.style.display = 'none';
        }, 5000);
    }
};