document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('register-form');
	if (!form) return;

	const fullName = form.querySelector('input[name="full_name"]');
	const email = form.querySelector('input[name="email"]');
	const password = form.querySelector('input[name="password"]');
	const confirm = form.querySelector('input[name="confirm_password"]');
	const terms = form.querySelector('input[name="terms"]');
	const submitBtn = form.querySelector('button[type="submit"]');

	const formErrors = document.getElementById('register-errors');
	const emailError = document.getElementById('email-error');
	const fullNameError = document.getElementById('full_name-error');
	const confirmError = document.getElementById('confirm_password-error');
	const strengthEl = document.getElementById('password-strength');
	const meter = document.getElementById('pw-meter');

	function calculateStrength(pw) {
		let score = 0;
		if (pw.length >= 8) score++;
		if (/[A-Z]/.test(pw)) score++;
		if (/[0-9]/.test(pw)) score++;
		if (/[^A-Za-z0-9]/.test(pw)) score++;
		return score; // 0..4
	}

	function updateStrengthUI() {
		if (!strengthEl || !meter || !password) return;
		const s = calculateStrength(password.value || '');
		const labels = ['Very weak', 'Weak', 'Okay', 'Good', 'Strong'];
		strengthEl.textContent = password.value ? labels[s] : '';
		strengthEl.dataset.score = s;
		meter.value = s;
	}

	function clearFieldErrors() {
		[emailError, fullNameError, confirmError].forEach(function (el) {
			if (!el) return;
			el.textContent = '';
			el.classList.add('visually-hidden');
		});

		if (formErrors) {
			formErrors.style.display = 'none';
			formErrors.innerHTML = '';
		}
	}

	function setFieldError(el, msg) {
		if (!el) return;
		el.textContent = msg;
		el.classList.remove('visually-hidden');
	}

	function validateFormFields() {
		clearFieldErrors();
		let valid = true;

		if (!fullName.value.trim()) {
			setFieldError(fullNameError, 'Please enter your name');
			valid = false;
		}

		if (!email.value || !email.value.includes('@')) {
			setFieldError(emailError, 'Please enter a valid email address');
			valid = false;
		}

		if (password.value.length < 8) {
			setFieldError(strengthEl, 'Password must be at least 8 characters');
			valid = false;
		}

		if (password.value !== confirm.value) {
			setFieldError(confirmError, 'Passwords do not match');
			valid = false;
		}

		if (!terms.checked) {
			valid = false;
		}

		return valid;
	}

	document.querySelectorAll('.toggle-password').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const targetId = this.getAttribute('data-target');
			const input = document.getElementById(targetId);
			if (!input) return;

			if (input.type === 'password') {
				input.type = 'text';
				this.textContent = 'Hide';
				this.setAttribute('aria-pressed', 'true');
			} else {
				input.type = 'password';
				this.textContent = 'Show';
				this.setAttribute('aria-pressed', 'false');
			}
		});
	});

	if (password) {
		password.addEventListener('input', function () {
			updateStrengthUI();
			if (strengthEl) {
				strengthEl.classList.remove('error');
			}
			toggleSubmitState();
		});
	}

	[fullName, email, confirm, terms].forEach(function (el) {
		if (!el) return;
		el.addEventListener('input', toggleSubmitState);
		el.addEventListener('change', toggleSubmitState);
	});

	function toggleSubmitState() {
		const ok = validateFormFields();
		submitBtn.disabled = !ok;

		if (submitBtn.disabled) {
			submitBtn.classList.remove('btn-primary');
		} else {
			submitBtn.classList.add('btn-primary');
		}
	}

	submitBtn.disabled = true;
	submitBtn.classList.remove('btn-primary');
	updateStrengthUI();

	form.addEventListener('submit', function (e) {
		clearFieldErrors();

		if (!validateFormFields()) {
			e.preventDefault();
			if (formErrors) {
				formErrors.innerHTML = '<strong>Please fix the errors below.</strong>';
				formErrors.style.display = 'block';
				formErrors.focus();
			}
		}
	});
});

