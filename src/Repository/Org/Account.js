document.addEventListener(
    "DOMContentLoaded",
    () => {

        const form =
            document.getElementById(
                "accountForm"
            );

        if (form) {

            form.addEventListener(
                "submit",
                function (event) {

                    const email =
                        document.querySelector(
                            'input[name="email"]'
                        ).value;

                    if (!email.includes("@")) {

                        event.preventDefault();

                        alert(
                            "Voer een geldig e-mailadres in."
                        );
                    }
                }
            );
        }
    }
);