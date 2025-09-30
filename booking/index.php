<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$settings = read_json(BOOKING_SETTINGS_FILE);
$sessionTypes = $settings['session_types'] ?? [];
$discoverySources = $settings['discovery_sources'] ?? [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Termin buchen · Studio Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {--color-dark:#7A6F64;--color-medium:#B8A99A;--color-light:#D8CEC3;--color-lighter:#EEEDED;}
        * {box-sizing:border-box;}
        body {margin:0;font-family:'Montserrat',sans-serif;background:linear-gradient(145deg,#EEEDED,#D8CEC3);color:#3b3128;}
        header {padding:3rem 6vw;text-align:center;color:#fff;background:linear-gradient(135deg,rgba(122,111,100,.85),rgba(184,169,154,.8));}
        h1 {margin:0;font-size:clamp(2.5rem,4vw,3.4rem);} 
        main {max-width:960px;margin:-60px auto 4rem;padding:0 2rem;}
        .booking-card {background:#fff;border-radius:28px;box-shadow:0 30px 90px rgba(122,111,100,.18);overflow:hidden;}
        .steps {display:flex;justify-content:space-between;padding:1.5rem 2rem;background:#f8f3ef;}
        .steps div {display:flex;align-items:center;gap:.6rem;font-weight:600;color:#7A6F64;}
        .steps span {width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#EEEDED;color:#7A6F64;font-weight:700;}
        .steps .active span {background:#7A6F64;color:#fff;}
        form {padding:2rem 2.5rem;display:grid;gap:2rem;}
        .section {display:none;animation:fade .4s ease forwards;}
        .section.active {display:grid;gap:1.5rem;}
        label {font-weight:600;margin-bottom:.4rem;display:block;}
        input,select,textarea {width:100%;padding:.85rem 1rem;border:1px solid #D8CEC3;border-radius:14px;font-size:1rem;background:#fdfdfd;}
        textarea {min-height:120px;}
        .actions {display:flex;justify-content:space-between;gap:1rem;}
        button {padding:.9rem 1.6rem;border:none;border-radius:999px;font-weight:600;font-size:1rem;cursor:pointer;}
        .btn-prev {background:#EEEDED;color:#7A6F64;}
        .btn-next {background:#7A6F64;color:#fff;box-shadow:0 15px 40px rgba(122,111,100,.25);}
        .availability {display:grid;gap:1rem;}
        .slots {display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:.75rem;}
        .slot {padding:.9rem;border-radius:14px;text-align:center;background:#EEEDED;color:#7A6F64;font-weight:600;cursor:pointer;transition:transform .2s;}
        .slot:hover {transform:translateY(-2px);} 
        .slot.disabled {opacity:.4;cursor:not-allowed;text-decoration:line-through;}
        .slot.selected {background:#7A6F64;color:#fff;}
        .gdpr {background:#f8f3ef;padding:1.2rem;border-radius:16px;display:grid;gap:1rem;}
        .gdpr label {display:flex;align-items:flex-start;gap:.75rem;font-weight:500;cursor:pointer;}
        .gdpr input {width:auto;margin-top:.2rem;}
        .summary {background:#f8f3ef;padding:1.5rem;border-radius:20px;}
        .status {display:flex;align-items:center;gap:.6rem;color:#7A6F64;font-weight:600;}
        #toast {position:fixed;bottom:1.5rem;right:1.5rem;background:#7A6F64;color:#fff;padding:1rem 1.5rem;border-radius:12px;opacity:0;transform:translateY(20px);transition:all .3s ease;}
        #toast.visible {opacity:1;transform:translateY(0);} 
        @keyframes fade {from {opacity:0;transform:translateY(10px);} to {opacity:1;transform:translateY(0);} }
        @media (max-width:680px){.steps{flex-direction:column;gap:1rem;}}
    </style>
</head>
<body>
<header>
    <h1>Termin buchen</h1>
    <p style="max-width:700px;margin:1rem auto 0;font-size:1.1rem;">Planen Sie Ihr persönliches Fotoshooting im Studio Lumière. Bitte wählen Sie Wunschdatum und Uhrzeit.</p>
</header>
<main>
    <div class="booking-card">
        <div class="steps">
            <div class="step active" data-step="0"><span>1</span> Persönliche Daten</div>
            <div class="step" data-step="1"><span>2</span> Termin</div>
            <div class="step" data-step="2"><span>3</span> Datenschutz</div>
        </div>
        <form id="booking-form">
            <section class="section active" data-section="0">
                <div>
                    <label>Vor- und Nachname *</label>
                    <input type="text" name="name" required />
                </div>
                <div>
                    <label>E-Mail *</label>
                    <input type="email" name="email" required />
                </div>
                <div>
                    <label>Instagram</label>
                    <input type="text" name="instagram" placeholder="@ihrprofil" />
                </div>
                <div>
                    <label>Art des Shootings *</label>
                    <select name="session_type" required>
                        <option value="">Bitte wählen</option>
                        <?php foreach ($sessionTypes as $type): ?>
                            <option value="<?= sanitize_text($type) ?>"><?= sanitize_text($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Wie haben Sie uns gefunden?</label>
                    <select name="discovery">
                        <option value="">Bitte wählen</option>
                        <?php foreach ($discoverySources as $source): ?>
                            <option value="<?= sanitize_text($source) ?>"><?= sanitize_text($source) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Ihre Nachricht</label>
                    <textarea name="message" placeholder="Was dürfen wir über Ihre Vorstellungen wissen?"></textarea>
                </div>
            </section>
            <section class="section" data-section="1">
                <div class="availability">
                    <div>
                        <label>Wunschtermin *</label>
                        <input type="date" name="date" id="date-picker" required />
                    </div>
                    <div>
                        <label>Verfügbare Zeiten *</label>
                        <div class="slots" id="time-slots"></div>
                        <input type="hidden" name="time" id="selected-time" required />
                    </div>
                </div>
            </section>
            <section class="section" data-section="2">
                <div class="summary" id="summary"></div>
                <div class="gdpr">
                    <label><input type="checkbox" name="gdpr" required /> Ich stimme der Verarbeitung meiner Daten zur Terminvereinbarung gemäß <a href="../datenschutz.html" target="_blank">Datenschutzerklärung</a> zu.</label>
                    <label><input type="checkbox" name="marketing" /> Ich möchte Neuigkeiten und Angebote per E-Mail erhalten.</label>
                </div>
                <div class="status" id="status-message"></div>
            </section>
            <div class="actions">
                <button type="button" class="btn-prev" id="prev">Zurück</button>
                <button type="button" class="btn-next" id="next">Weiter</button>
            </div>
        </form>
    </div>
</main>
<div id="toast"></div>
<script>
const steps = document.querySelectorAll('.step');
const sections = document.querySelectorAll('.section');
const prevBtn = document.getElementById('prev');
const nextBtn = document.getElementById('next');
const form = document.getElementById('booking-form');
const datePicker = document.getElementById('date-picker');
const slotsContainer = document.getElementById('time-slots');
const selectedTimeInput = document.getElementById('selected-time');
const summary = document.getElementById('summary');
const statusMessage = document.getElementById('status-message');
const toast = document.getElementById('toast');
let currentStep = 0;

function showStep(step) {
    currentStep = step;
    sections.forEach(section => section.classList.toggle('active', Number(section.dataset.section) === step));
    steps.forEach(item => item.classList.toggle('active', Number(item.dataset.step) === step));
    prevBtn.style.visibility = step === 0 ? 'hidden' : 'visible';
    nextBtn.textContent = step === sections.length - 1 ? 'Buchung senden' : 'Weiter';
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 4000);
}

function validateStep(step) {
    if (step === 0) {
        const fields = form.querySelectorAll('[data-section="0"] input, [data-section="0"] select');
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
    }
    if (step === 1) {
        if (!datePicker.value) {
            showToast('Bitte wählen Sie ein Datum.');
            return false;
        }
        if (!selectedTimeInput.value) {
            showToast('Bitte wählen Sie eine Uhrzeit.');
            return false;
        }
    }
    return true;
}

function updateSummary() {
    const data = new FormData(form);
    summary.innerHTML = `
        <h3 style="margin-top:0;color:#7A6F64;">Zusammenfassung</h3>
        <p><strong>Name:</strong> ${data.get('name') ?? ''}</p>
        <p><strong>E-Mail:</strong> ${data.get('email') ?? ''}</p>
        <p><strong>Shooting:</strong> ${data.get('session_type') ?? ''}</p>
        <p><strong>Termin:</strong> ${data.get('date') ?? ''} · ${data.get('time') ?? ''}</p>
        <p><strong>Instagram:</strong> ${data.get('instagram') ?? ''}</p>
        <p><strong>Nachricht:</strong> ${data.get('message') ?? ''}</p>
    `;
}

async function loadSlots(date) {
    slotsContainer.innerHTML = '<p>Verfügbarkeit wird geladen...</p>';
    try {
        const response = await fetch('booking-api.php?action=get_availability&date=' + encodeURIComponent(date));
        const data = await response.json();
        slotsContainer.innerHTML = '';
        if (data.slots?.length) {
            data.slots.forEach(slot => {
                const button = document.createElement('div');
                button.className = 'slot';
                button.textContent = slot.time;
                if (!slot.available) {
                    button.classList.add('disabled');
                }
                if (selectedTimeInput.value === slot.time) {
                    button.classList.add('selected');
                }
                button.addEventListener('click', () => {
                    if (slot.available) {
                        selectedTimeInput.value = slot.time;
                        document.querySelectorAll('.slot').forEach(el => el.classList.remove('selected'));
                        button.classList.add('selected');
                    }
                });
                slotsContainer.appendChild(button);
            });
        } else {
            slotsContainer.innerHTML = '<p>Keine Slots verfügbar.</p>';
        }
    } catch (error) {
        slotsContainer.innerHTML = '<p>Fehler beim Laden der Verfügbarkeit.</p>';
    }
}

datePicker.addEventListener('change', event => {
    const value = event.target.value;
    if (!value) return;
    selectedTimeInput.value = '';
    loadSlots(value);
});

prevBtn.addEventListener('click', () => {
    if (currentStep > 0) {
        showStep(currentStep - 1);
    }
});

nextBtn.addEventListener('click', async () => {
    if (!validateStep(currentStep)) return;
    if (currentStep < sections.length - 1) {
        if (currentStep === 1) {
            updateSummary();
        }
        showStep(currentStep + 1);
        return;
    }
    const formData = new FormData(form);
    try {
        nextBtn.disabled = true;
        nextBtn.textContent = 'Sende ...';
        const response = await fetch('booking-api.php?action=create_booking', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        nextBtn.disabled = false;
        nextBtn.textContent = 'Buchung senden';
        if (data.error) {
            statusMessage.textContent = data.error;
            statusMessage.style.color = '#c0392b';
            return;
        }
        statusMessage.innerHTML = `✅ Anfrage erhalten. Bestätigung: <a href="booking-confirmation.php?token=${data.token}" target="_blank">Buchung anzeigen</a>`;
        statusMessage.style.color = '#27ae60';
        showToast('Buchung erfolgreich übermittelt.');
        form.reset();
        selectedTimeInput.value = '';
        slotsContainer.innerHTML = '';
        showStep(0);
    } catch (error) {
        nextBtn.disabled = false;
        nextBtn.textContent = 'Buchung senden';
        statusMessage.textContent = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
        statusMessage.style.color = '#c0392b';
    }
});

showStep(0);
</script>
</body>
</html>
