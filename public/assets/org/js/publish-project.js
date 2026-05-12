// wait for page to load
document.addEventListener('DOMContentLoaded', function() {

    // get the fields we need
    const form = document.querySelector('form');
    const nameField = document.getElementById('name');
    const descField = document.getElementById('description');
    const fileField = document.getElementById('projectFile');

    // exit if form doesn't exist
    if (!form) return;

    // basic form check
    form.onsubmit = function(e) {

        // check project name
        if (nameField.value == "") {
            alert("please enter a project name");
            e.preventDefault();
            return false;
        }

        // check description length
        if (descField.value.length < 10) {
            alert("description is too short! minimum 10 characters");
            e.preventDefault();
            return false;
        }

        if (fileField && fileField.files.length > 0) {
            // simple file type + size check
            const file = fileField.files[0];
            const maxSize = 10 * 1024 * 1024;
            const allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip'];
            const ext = file.name.split('.').pop().toLowerCase();

            if (allowed.indexOf(ext) === -1) {
                alert('please choose a valid file type');
                e.preventDefault();
                return false;
            }

            if (file.size > maxSize) {
                alert('file is too large (max 10MB)');
                e.preventDefault();
                return false;
            }
        }

        // completed form check
        console.log("form is valid - submitting");
        return true;
    };

    // random log message
    console.log("publish page ready");
});
