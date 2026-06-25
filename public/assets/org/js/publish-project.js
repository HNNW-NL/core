// Wait until the HTML document has fully loaded before running validation logic.
document.addEventListener('DOMContentLoaded', function() {

    // Get the form and input fields that need to be validated.
    const form = document.querySelector('form');
    const nameField = document.getElementById('name');
    const descField = document.getElementById('description');
    const fileField = document.getElementById('projectFile');

    // Stop the script if the page does not contain a form.
    if (!form) return;

    // Run validation when the form is submitted.
    form.onsubmit = function(e) {

        // Require a project name before allowing submission.
        if (nameField.value == "") {
            alert("please enter a project name");
            e.preventDefault();
            return false;
        }

        // Require a useful description with at least 10 characters.
        if (descField.value.length < 10) {
            alert("description is too short! minimum 10 characters");
            e.preventDefault();
            return false;
        }

        // Validate the uploaded file only when a file has been selected.
        if (fileField && fileField.files.length > 0) {
            // Read the selected file and define upload limits.
            const file = fileField.files[0];
            const maxSize = 10 * 1024 * 1024;
            const allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip'];

            // Get the file extension so it can be checked against the allowed list.
            const ext = file.name.split('.').pop().toLowerCase();

            // Block unsupported file extensions.
            if (allowed.indexOf(ext) === -1) {
                alert('please choose a valid file type');
                e.preventDefault();
                return false;
            }

            // Block files larger than 10MB.
            if (file.size > maxSize) {
                alert('file is too large (max 10MB)');
                e.preventDefault();
                return false;
            }
        }

        // Allow the form to submit after all checks pass.
        console.log("form is valid - submitting");
        return true;
    };

    // Confirm in the browser console that the script has loaded.
    console.log("publish page ready");
});
