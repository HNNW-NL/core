// wait for page to load
document.addEventListener('DOMContentLoaded', function() {

    const form = document.querySelector('form');
    const nameField = document.getElementById('name');
    const descField = document.getElementById('description');

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

        // completed form check
        console.log("form is valid - submitting");
        return true;
    };

    // random log message
    console.log("publish page ready");
});
