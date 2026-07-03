const input = document.getElementById("invite-input");
const list = document.getElementById("profile-list");
const output = document.getElementById("selected-list");
const post = document.getElementById("invite-form");

let selected = JSON.parse(sessionStorage.getItem("selectedParticipants")) || [];
let timeout;

document.querySelector("form").addEventListener("submit", () => {
    sessionStorage.setItem("clearList", "1");
});

// selected in session storage
function save() {
    sessionStorage.setItem("selectedParticipants", JSON.stringify(selected));
}

// render selected in html
function renderSelected() {
    output.innerHTML = selected
        .map((p, i) => `
            <li class="profile-item" data-id="${p.id}" data-name="${p.displayName}">
                <input type="hidden" name="items[${i}][id]" value="${p.id}">
                <input type="hidden" name="items[${i}][displayName]" value="${p.displayName}">
                ${p.displayName} (${p.id})
            </li>
        `).join("");
}

// highlight selected profiles in search
function updateUI() {
    const items = list.querySelectorAll(".profile-item");

    items.forEach(item => {
        const id = item.dataset.id;
        item.classList.toggle(
            "active",
            selected.some(p => p.id === id)
        );
    });

    renderSelected();
}


// check for input and send ajax request to the controller to search for profiles
input.addEventListener("input", () => {
    clearTimeout(timeout);

    const q = input.value;

    timeout = setTimeout(async () => {
        if (q.length < 2) {
            list.innerHTML = "";
            return;
        }

        const res = await fetch(
            window.location.pathname + "?q=" + encodeURIComponent(q),
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }
        );

        const data = await res.json();

        if (data.length === 0) {
            list.innerHTML = `
                <li class="no-results">
                    No profiles found
                </li>
            `;
            return;
        }

        list.innerHTML = data.map(p => `
            <li class="profile-item"
                data-id="${p.id}"
                data-name="${p.displayName}">
                ${p.displayName} (${p.id})
            </li>
        `).join("");

        updateUI();

    }, 200);
});



// check for click on highlighted profiles and remove from selected
list.addEventListener("click", (e) => {
    const item = e.target.closest(".profile-item");
    if (!item) return;

    const id = item.dataset.id;
    const displayName = item.dataset.name;

    const exists = selected.find(p => p.id === id);

    if (exists) {
        selected = selected.filter(p => p.id !== id);
    } else {
        selected.push({ id, displayName });
    }

    save();
    updateUI();
});



// clear selected state after post
if (sessionStorage.getItem("clearList") === "1") {
    selected.length = 0;
    sessionStorage.removeItem("clearList");
    console.log("List cleared after POST");
}

updateUI();
