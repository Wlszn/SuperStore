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
        window.setLocalizedText(el, '');
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
            window.setLocalizedText(errorSlot, fieldMessage);
        }
    });

    if (firstInvalidInput !== null) {
        firstInvalidInput.focus();
    }
}

/** Shows the green (success) or red (error) message box above the form. */
function showMessage(messageEl, text, isSuccess) {
    window.setLocalizedText(messageEl, text);
    messageEl.className = 'form-message ' + (isSuccess ? 'success' : 'error');
    messageEl.hidden = false;
}

/** Hides the message box again (called before every new submit). */
function hideMessage(messageEl) {
    messageEl.hidden = true;
    window.setLocalizedText(messageEl, '');
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

function wirePhoneNumberInput(input) {
    input.addEventListener('input', () => {
        const digitsBeforeCursor = input.value.slice(0, input.selectionStart).replace(/\D/g, '').length;
        const digits = input.value.replace(/\D/g, '').slice(0, 15);
        const groups = digits.length === 11 && digits.startsWith('1')
            ? [digits.slice(0, 1), digits.slice(1, 4), digits.slice(4, 7), digits.slice(7)]
            : [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 10), digits.slice(10)];
        const formatted = groups.filter(Boolean).join(' ');
        if (input.value === formatted) return;

        input.value = formatted;
        let cursor = 0;
        let remaining = digitsBeforeCursor;
        while (cursor < formatted.length && remaining > 0) {
            if (formatted[cursor] !== ' ') remaining--;
            cursor++;
        }
        input.setSelectionRange(cursor, cursor);
    });

    input.form.addEventListener('formdata', event => {
        event.formData.set(input.name, input.value.replace(/\D/g, ''));
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
    const phone = fields['phone'].value.replace(/ /g, '');
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
    } else if (!/^[0-9]{7,15}$/.test(phone)) {
        errors.phone = 'Phone number must be 7 to 15 digits.';
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
