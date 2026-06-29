const items = document.querySelectorAll(".participant-item");
const output = document.getElementById("selected-list");

let selected = JSON.parse(localStorage.getItem("selectedParticipants")) || [];

function save() {
    localStorage.setItem("selectedParticipants", JSON.stringify(selected));
}

function renderSelected() {
    output.innerHTML = selected
        .map(p => `<div>${p.name} (${p.id})</div>`)
        .join("");
}

function updateUI() {
    items.forEach(item => {
        const id = item.dataset.id;

        if (selected.find(p => p.id === id)) {
            item.classList.add("active");
        } else {
            item.classList.remove("active");
        }
    });

    renderSelected();
}

items.forEach(item => {
    item.addEventListener("click", () => {
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
});

updateUI();
