const form = document.getElementById("adminLoginForm");
const email = document.getElementById("email");
const password = document.getElementById("password");
const emailError = document.getElementById("emailError");
const passwordError = document.getElementById("passwordError");
const togglePassword = document.getElementById("togglePassword");

togglePassword.addEventListener("click", () => {
    const isPassword = password.type === "password";
    password.type = isPassword ? "text" : "password";
    togglePassword.textContent = isPassword ? "Hide" : "Show";
});

form.addEventListener("submit", function (e) {
    let valid = true;

    emailError.textContent = "";
    passwordError.textContent = "";

    const emailValue = email.value.trim();
    const passwordValue = password.value.trim();

    if (!emailValue) {
        emailError.textContent = "Email is required.";
        valid = false;
    } else if (!/^\S+@\S+\.\S+$/.test(emailValue)) {
        emailError.textContent = "Enter a valid email address.";
        valid = false;
    }

    if (!passwordValue) {
        passwordError.textContent = "Password is required.";
        valid = false;
    } else if (passwordValue.length < 6) {
        passwordError.textContent = "Password must be at least 6 characters.";
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
    }
});