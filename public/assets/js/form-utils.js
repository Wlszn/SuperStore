async function postForm(action, form) {
    const response = await fetch('/' + action, {
        method: 'POST',
        body: new FormData(form),
    });
    return response.json();
}

/** Removes every error message and red border from a form. */
function clearFormErrors(form) {
    form.querySelectorAll('.field-error').forEach(function (el) {
        el.textContent = '';
    });
    form.querySelectorAll('.invalid').forEach(function (el) {
        el.classList.remove('invalid');
    });
}

function showFormErrors(form, errors) {
    clearFormErrors(form);

    let firstInvalidInput = null;

    Object.entries(errors || {}).forEach(function ([field, fieldMessage]) {
        const input = form.querySelector('[name="' + field + '"]');
        const errorSlot = form.querySelector('#error-' + field);

        if (input) {
            input.classList.add('invalid');
            if (firstInvalidInput === null) {
                firstInvalidInput = input;
            }
        }
        if (errorSlot) {
            errorSlot.textContent = fieldMessage;
        }
    });

    if (firstInvalidInput !== null) {
        firstInvalidInput.focus();
    }
}

/** Shows the green (success) or red (error) message box above the form. */
function showMessage(messageEl, text, isSuccess) {
    messageEl.textContent = text;
    messageEl.className = 'form-message ' + (isSuccess ? 'success' : 'error');
    messageEl.hidden = false;
}

/** Hides the message box again (called before every new submit). */
function hideMessage(messageEl) {
    messageEl.hidden = true;
    messageEl.textContent = '';
    messageEl.className = 'form-message';
}

/**
 * Lets the user reveal what they typed in a password field.
 * checkboxId: the "Show password" checkbox.
 * passwordInputIds: the password inputs to toggle (e.g. ['password', 'confirm_password']).
 */
function wireShowPasswordToggle(checkboxId, passwordInputIds) {
    const checkbox = document.getElementById(checkboxId);
    if (!checkbox) {
        return;
    }
    checkbox.addEventListener('change', function () {
        const type = checkbox.checked ? 'text' : 'password';
        passwordInputIds.forEach(function (id) {
            const input = document.getElementById(id);
            if (input) {
                input.type = type;
            }
        });
    });
}

/**
 * Checks name, email, phone and address in the browser before sending.
 * Returns an errors object (empty when everything is valid).
 */
function validateCustomerFields(form) {
    const fields = form.elements;
    const errors = {};

    const name = fields['name'].value.trim();
    const email = fields['email'].value.trim();
    const phone = fields['phone'].value.trim();
    const address = fields['address'].value.trim();

    if (name === '') {
        errors.name = 'Please enter the full name.';
    } else if (name.length < 2) {
        errors.name = 'Name must be at least 2 characters long.';
    } else if (name.length > 50) {
        errors.name = 'Name cannot be longer than 50 characters.';
    }

    if (email === '') {
        errors.email = 'Please enter an email address.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = 'This email address does not look valid. Example: name@example.com';
    } else if (email.length > 100) {
        errors.email = 'Email cannot be longer than 100 characters.';
    }

    if (phone === '') {
        errors.phone = 'Please enter a phone number.';
    } else if (!/^[0-9+\-() ]{7,15}$/.test(phone)) {
        errors.phone = 'Phone number must be 7 to 15 digits (spaces, dashes and brackets are allowed).';
    }

    if (address === '') {
        errors.address = 'Please enter an address.';
    } else if (address.length > 255) {
        errors.address = 'Address cannot be longer than 255 characters.';
    }

    return errors;
}

/**
 * Checks the two password fields on the signup page.
 * Returns an errors object (empty when everything is valid).
 */
function validatePasswordFields(form) {
    const fields = form.elements;
    const errors = {};

    const password = fields['password'].value;
    const confirmPassword = fields['confirm_password'].value;

    if (password === '') {
        errors.password = 'Please choose a password.';
    } else if (password.length < 8) {
        errors.password = 'Password must be at least 8 characters long.';
    } else if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        errors.password = 'Password must contain at least one letter and one number.';
    }

    if (confirmPassword === '') {
        errors.confirm_password = 'Please type your password again to confirm it.';
    } else if (password !== '' && password !== confirmPassword) {
        errors.confirm_password = 'The two passwords do not match. Please type them again.';
    }

    return errors;
}
