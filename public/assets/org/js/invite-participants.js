const input = document.getElementById("invite-input");
const list = document.getElementById("profile-list");
const listItems = document.querySelectorAll(".profile-item");
const output = document.getElementById("selected-list");

let selected = JSON.parse(localStorage.getItem("selectedParticipants")) || [];
let timeout;

function save() {
    localStorage.setItem("selectedParticipants", JSON.stringify(selected));
}

function renderSelected() {
    output.innerHTML = selected
        .map(p => `<div>${p.name} (${p.id})</div>`)
        .join("");
}

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

        list.innerHTML = data.map(p => `
            <li class="profile-item"
                data-id="${p.id}"
                data-name="${p.displayName}">
                ${p.displayName} (${p.id})
            </li>
        `).join("");

        updateUI();

    }, 250);
});


list.addEventListener("click", (e) => {
    const item = e.target.closest(".profile-item");
    if (!item) return;

    const id = item.dataset.id;
    const name = item.dataset.name;

    const exists = selected.find(p => p.id === id);

    if (exists) {
        selected = selected.filter(p => p.id !== id);
    } else {
        selected.push({ id, name });
    }

    save();
    updateUI();
});

updateUI();
