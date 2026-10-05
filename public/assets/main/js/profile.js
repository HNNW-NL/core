// profielpagina: voorbeeld van de gekozen profielfoto en de popup om de locatie aan te passen

// stoppen als de velden er niet zijn, anders geeft de regel eronder een fout
const upload = document.getElementById("upload");
const preview = document.getElementById("preview");

if (upload && preview) {
    upload.addEventListener("change", function () {
        const file = this.files[0];

        if (file) {
            const reader = new FileReader();

            reader.onload = function () {
                preview.src = reader.result;
            };

            reader.readAsDataURL(file);
        }
    });
}

// deze drie functies worden aangeroepen via onclick in het template, daarom staan ze los (globaal)
function openLocationPopup() {
    document.getElementById('location-popup').style.display = 'block';
    document.getElementById('popup-overlay').style.display = 'block';
}

function closePopup() {
    document.getElementById('location-popup').style.display = 'none';
    document.getElementById('popup-overlay').style.display = 'none';
}

function saveLocation() {
    const newLocation = document.getElementById('new-location').value;

    if (!newLocation) {
        alert('Kies eerst een stad alstublieft.');
        return;
    }

    fetch('/profile/location', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ location: newLocation })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('current-location').textContent = data.location;
            closePopup();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
}
