console.log

const passwordInput = document.getElementById("password");
const passwordToggle = document.getElementById("passwordToggle");

if (passwordInput && passwordToggle) {
	passwordToggle.addEventListener("click", function () {
		const shouldShowPassword = passwordInput.type === "password";

		passwordInput.type = shouldShowPassword ? "text" : "password";
		passwordToggle.textContent = shouldShowPassword ? "🙈" : "👁";
		passwordToggle.setAttribute(
			"aria-label",
			shouldShowPassword ? "Hide password" : "Show password"
		);
		passwordToggle.setAttribute(
			"aria-pressed",
			shouldShowPassword ? "true" : "false"
		);
	});
}
