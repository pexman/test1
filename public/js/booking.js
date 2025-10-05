(function () {
    const dateInput = document.getElementById('bookingDate');
    const timeSelect = document.getElementById('bookingTime');

    if (!dateInput || !timeSelect) {
        return;
    }

    const renderSlots = (slots) => {
        timeSelect.innerHTML = '';
        if (!slots.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Sin horarios disponibles';
            timeSelect.appendChild(option);
            return;
        }

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selecciona un horario';
        timeSelect.appendChild(placeholder);

        slots.forEach((slot) => {
            const option = document.createElement('option');
            option.value = slot;
            option.textContent = slot;
            timeSelect.appendChild(option);
        });
    };

    dateInput.addEventListener('change', async () => {
        const date = dateInput.value;
        if (!date) {
            return;
        }

        const response = await fetch(`/public/api/availability.php?date=${encodeURIComponent(date)}`);
        if (!response.ok) {
            renderSlots([]);
            return;
        }

        const data = await response.json();
        renderSlots(data.slots || []);
    });
})();
